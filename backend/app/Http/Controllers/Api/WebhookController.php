<?php

namespace App\Http\Controllers\Api;

use App\Models\ProcessedMessage;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\TransactionParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * WebhookController
 *
 * Receives incoming WhatsApp messages from the Baileys gateway sidecar
 * and processes them into financial transactions or command responses.
 *
 * Implements:
 * - FR-001, FR-002: User identification and auto-initialization
 * - FR-003, FR-014: Message parsing with user guidance on failure
 * - FR-006, FR-007: Atomic transaction recording with confirmation reply
 * - FR-008: Balance and summary inquiry commands
 * - FR-005: Category management commands
 * - FR-012: Message idempotency deduplication
 * - FR-013: Undo command
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly TransactionParserService $parser,
    ) {}

    /**
     * POST /api/webhook/whatsapp
     *
     * Entry point for all incoming WhatsApp messages from the Baileys gateway.
     * Returns a structured reply that the gateway uses to send the response back.
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate webhook secret header (FR-012 security)
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        if ($secret && $request->header('X-Gateway-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'message_id'   => 'required|string',
            'from_jid'     => 'required|string',
            'push_name'    => 'nullable|string',
            'message_text' => 'required|string',
            'timestamp'    => 'nullable|integer',
        ]);

        $messageId   = $validated['message_id'];
        $fromJid     = $validated['from_jid'];
        $pushName    = $validated['push_name'] ?? '';
        $messageText = trim($validated['message_text']);

        // --- Idempotency Check — FR-012 ---
        if (ProcessedMessage::where('message_id', $messageId)->exists()) {
            Log::info("Webhook: Duplicate message skipped", ['message_id' => $messageId]);
            return response()->json(['action' => 'IGNORE', 'reason' => 'duplicate']);
        }

        // Record as processed
        ProcessedMessage::create([
            'message_id' => $messageId,
            'user_jid'   => $fromJid,
            'received_at' => now(),
        ]);

        // --- User Auto-Init — FR-001, FR-002 ---
        $user = $this->financeService->findOrCreateUser($fromJid, $pushName);

        $isNewUser = $user->wasRecentlyCreated;

        if ($isNewUser) {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->buildWelcomeMessage($user),
            ]);
        }

        // --- Command Routing ---
        $lowerText = strtolower($messageText);

        // Balance inquiry
        if (preg_match('/^saldo$/i', $messageText)) {
            return $this->handleBalanceInquiry($user);
        }

        // Rekap hari ini
        if (preg_match('/^rekap hari ini$/i', $messageText)) {
            return $this->handleDailySummary($user);
        }

        // Rekap bulan ini
        if (preg_match('/^rekap( bulan ini)?$/i', $messageText)) {
            return $this->handleMonthlySummary($user);
        }

        // Undo / batal
        if (preg_match('/^(batal|hapus transaksi terakhir|undo)$/i', $messageText)) {
            return $this->handleUndo($user);
        }

        // Export Excel
        if (preg_match('/^(export excel|laporan excel|download laporan)$/i', $messageText)) {
            return $this->handleExcelExport($user);
        }

        // Category list
        if (preg_match('/^(kategori|daftar kategori|list kategori)$/i', $messageText)) {
            return $this->handleListCategories($user);
        }

        // Add category: "tambah kategori <name>"
        if (preg_match('/^tambah kategori\s+(.+)$/i', $messageText, $matches)) {
            return $this->handleAddCategory($user, trim($matches[1]));
        }

        // Help / menu
        if (preg_match('/^(bantuan|help|menu|\?)$/i', $messageText)) {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->buildHelpMessage(),
            ]);
        }

        // --- Transaction Parsing — FR-003, FR-014 ---
        $parsed = $this->parser->parse($messageText);

        if (!$parsed) {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->buildUnrecognizedMessage($messageText),
            ]);
        }

        return $this->handleTransaction($user, $parsed);
    }

    // ---------------------------------------------------------------------------
    // Command Handlers
    // ---------------------------------------------------------------------------

    private function handleBalanceInquiry(User $user): JsonResponse
    {
        $balance = $this->financeService->getBalance($user);
        $daily   = $this->financeService->getDailySummary($user, now()->toDateString());

        $text = "💰 *Informasi Saldo Kamu*\n";
        $text .= "━━━━━━━━━━━━━━━━━\n";
        $text .= "💵 *Saldo Saat Ini*: " . $this->formatRupiah($balance) . "\n\n";
        $text .= "📅 *Transaksi Hari Ini* (" . now()->format('d M Y') . ")\n";
        $text .= "  ↑ Masuk : " . $this->formatRupiah($daily['total_income']) . "\n";
        $text .= "  ↓ Keluar: " . $this->formatRupiah($daily['total_expense']) . "\n";
        $text .= "  📊 Total: " . $daily['transaction_count'] . " transaksi";

        return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);
    }

    private function handleDailySummary(User $user): JsonResponse
    {
        $summary = $this->financeService->getDailySummary($user, now()->toDateString());

        $text = "📊 *Rekap Hari Ini* (" . now()->format('d M Y') . ")\n";
        $text .= "━━━━━━━━━━━━━━━━━\n";
        $text .= "💚 Pemasukan : " . $this->formatRupiah($summary['total_income']) . "\n";
        $text .= "❤️ Pengeluaran: " . $this->formatRupiah($summary['total_expense']) . "\n";
        $text .= "📝 Transaksi : " . $summary['transaction_count'] . " kali\n";
        $text .= "━━━━━━━━━━━━━━━━━\n";
        $text .= "💰 Saldo Kini : " . $this->formatRupiah($this->financeService->getBalance($user));

        return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);
    }

    private function handleMonthlySummary(User $user): JsonResponse
    {
        $summary = $this->financeService->getMonthlySummary($user, now()->year, now()->month);
        $period  = now()->translatedFormat('F Y');

        $text = "📊 *Rekap Bulan " . now()->format('M Y') . "*\n";
        $text .= "━━━━━━━━━━━━━━━━━\n";
        $text .= "💚 Total Masuk : " . $this->formatRupiah($summary['total_income']) . "\n";
        $text .= "❤️ Total Keluar : " . $this->formatRupiah($summary['total_expense']) . "\n";
        $text .= "💎 Net Savings : " . $this->formatRupiah($summary['net_savings']) . "\n";
        $text .= "━━━━━━━━━━━━━━━━━\n";

        if (!empty($summary['category_breakdown'])) {
            $text .= "🏷️ *Top Pengeluaran*:\n";
            foreach (array_slice($summary['category_breakdown'], 0, 5) as $cat) {
                $text .= "  {$cat['icon']} {$cat['category_name']}: " . $this->formatRupiah($cat['total_amount']) . "\n";
            }
        }

        $text .= "━━━━━━━━━━━━━━━━━\n";
        $text .= "💰 Saldo Sekarang: " . $this->formatRupiah($summary['current_balance']);

        return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);
    }

    private function handleUndo(User $user): JsonResponse
    {
        $result = $this->financeService->undoLastTransaction($user);

        if (!$result) {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "ℹ️ Tidak ada transaksi aktif yang bisa dibatalkan.",
            ]);
        }

        $voided = $result['voided'];
        $text   = "↩️ *Transaksi Berhasil Dibatalkan*\n";
        $text  .= "━━━━━━━━━━━━━━━━━\n";
        $text  .= "📝 {$voided->description}\n";
        $text  .= "💸 " . ($voided->type === 'EXPENSE' ? 'Pengeluaran' : 'Pemasukan') . ": " . $this->formatRupiah($voided->amount) . "\n";
        $text  .= "━━━━━━━━━━━━━━━━━\n";
        $text  .= "💰 Saldo Baru: " . $this->formatRupiah($result['new_balance']);

        return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);
    }

    private function handleExcelExport(User $user): JsonResponse
    {
        // Check if user has any transactions
        if ($user->transactions()->active()->count() === 0) {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "📋 Belum ada transaksi yang bisa di-ekspor.\n\nCoba catat pengeluaran dulu, contoh:\n_keluar 25000 makan siang_",
            ]);
        }

        $fileUrl = route('api.export.excel') . "?user_jid=" . urlencode($user->jid);

        return response()->json([
            'action'     => 'SEND_DOCUMENT',
            'reply_text' => "📊 Laporan keuangan pribadi Anda (Excel multi-sheet) sedang disiapkan...\n\n_Berisi: Dashboard ringkasan + tab bulanan dengan formula SUM otomatis._",
            'file_url'   => $fileUrl,
            'file_name'  => "Laporan_Keuangan_" . now()->format('Ymd') . ".xlsx",
            'mimetype'   => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function handleListCategories(User $user): JsonResponse
    {
        $categories = $this->financeService->getCategoriesForUser($user);

        $expenses = $categories->where('type', 'EXPENSE');
        $incomes  = $categories->where('type', 'INCOME');

        $text  = "🏷️ *Daftar Kategori Kamu*\n\n";
        $text .= "*Pengeluaran:*\n";
        foreach ($expenses as $cat) {
            $tag   = $cat->is_default ? '' : ' _(kustom)_';
            $text .= "  {$cat->icon} {$cat->name}{$tag}\n";
        }
        $text .= "\n*Pemasukan:*\n";
        foreach ($incomes as $cat) {
            $tag   = $cat->is_default ? '' : ' _(kustom)_';
            $text .= "  {$cat->icon} {$cat->name}{$tag}\n";
        }
        $text .= "\n_Ketik \"tambah kategori <nama>\" untuk menambah kategori baru._";

        return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);
    }

    private function handleAddCategory(User $user, string $categoryName): JsonResponse
    {
        try {
            $category = $this->financeService->addCustomCategory($user, $categoryName, 'EXPENSE');
            $text = "✅ Kategori *{$category->name}* berhasil ditambahkan!\n";
            $text .= "Sekarang kamu bisa pakai kategori ini saat catat transaksi.";
        } catch (\RuntimeException $e) {
            $text = "⚠️ " . $e->getMessage();
        }

        return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);
    }

    private function handleTransaction(User $user, array $parsed): JsonResponse
    {
        // Match category from hint
        $category = $this->financeService->matchCategory($user, $parsed['category_hint'], $parsed['type']);

        try {
            $transaction = $this->financeService->recordTransaction($user, [
                'type'        => $parsed['type'],
                'amount'      => $parsed['amount'],
                'description' => $parsed['description'],
                'category_id' => $category?->id,
            ]);

            $user->refresh();
            $typeLabel    = $parsed['type'] === 'EXPENSE' ? '💸 Pengeluaran' : '💰 Pemasukan';
            $typeEmoji    = $parsed['type'] === 'EXPENSE' ? '✅' : '✅';
            $typeTitle    = $parsed['type'] === 'EXPENSE' ? 'Catatan Pengeluaran Tersimpan' : 'Catatan Pemasukan Tersimpan';
            $categoryName = $category ? "{$category->icon} {$category->name}" : '📦 Lain-lain';

            $text  = "{$typeEmoji} *{$typeTitle}*\n";
            $text .= "━━━━━━━━━━━━━━━━━\n";

            if (!empty($parsed['description'])) {
                $text .= "📝 *Keterangan*: {$parsed['description']}\n";
            }

            $text .= "🏷️ *Kategori*: {$categoryName}\n";
            $text .= "{$typeLabel}: " . $this->formatRupiah($parsed['amount']) . "\n";
            $text .= "━━━━━━━━━━━━━━━━━\n";
            $text .= "💰 *Sisa Saldo*: " . $this->formatRupiah($user->current_balance) . "\n";
            $text .= "_Ketik \"batal\" jika ingin membatalkan transaksi ini._";

            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);

        } catch (\Exception $e) {
            Log::error("Webhook: Transaction failed", ['error' => $e->getMessage(), 'user' => $user->jid]);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "⚠️ Gagal menyimpan transaksi. Coba lagi ya!\n\nContoh format:\n_keluar 25000 makan siang_",
            ]);
        }
    }

    // ---------------------------------------------------------------------------
    // Message Builders
    // ---------------------------------------------------------------------------

    private function buildWelcomeMessage(User $user): string
    {
        $name = $user->display_name ?: 'Sobat';
        return "👋 Halo *{$name}*! Selamat datang di *WA Finance Bot* 💰\n\n"
            . "Bot ini membantu kamu catat keuangan harian langsung dari WhatsApp!\n\n"
            . "*Cara Pakai:*\n"
            . "  📤 _keluar 25000 makan siang_\n"
            . "  📥 _masuk 500000 gaji_\n"
            . "  💰 _saldo_\n"
            . "  📊 _rekap bulan ini_\n"
            . "  📋 _export excel_\n"
            . "  ↩️ _batal_ (untuk undo)\n\n"
            . "Ketik *bantuan* untuk panduan lengkap.\n\n"
            . "_Yuk mulai catat! 🚀_";
    }

    private function buildHelpMessage(): string
    {
        return "📚 *Panduan Lengkap WA Finance Bot*\n\n"
            . "*Catat Pengeluaran:*\n"
            . "  • `keluar 25000 makan siang`\n"
            . "  • `beli bensin 50rb`\n"
            . "  • `bayar listrik 150.000`\n"
            . "  • `-35k parkir`\n\n"
            . "*Catat Pemasukan:*\n"
            . "  • `masuk 500000 gaji`\n"
            . "  • `terima 250k bonus`\n"
            . "  • `+1.5jt freelance`\n\n"
            . "*Cek Saldo & Laporan:*\n"
            . "  • `saldo` — cek saldo terkini\n"
            . "  • `rekap hari ini` — ringkasan hari ini\n"
            . "  • `rekap bulan ini` — ringkasan bulan ini\n\n"
            . "*Lainnya:*\n"
            . "  • `batal` — batalkan transaksi terakhir\n"
            . "  • `export excel` — unduh laporan Excel\n"
            . "  • `kategori` — lihat daftar kategori\n"
            . "  • `tambah kategori <nama>` — tambah kategori baru\n\n"
            . "_Format nominal: 25000, 25.000, 25k, 25rb, 1.5jt_ 💡";
    }

    private function buildUnrecognizedMessage(string $message): string
    {
        return "🤔 Maaf, saya tidak mengerti maksud pesan:\n\"_{$message}_\"\n\n"
            . "*Format transaksi yang bisa dipahami:*\n"
            . "  📤 `keluar 25000 makan siang`\n"
            . "  📥 `masuk 500000 gaji`\n"
            . "  📤 `beli bensin 50rb`\n"
            . "  📥 `+1.5jt bonus proyek`\n\n"
            . "Atau ketik *bantuan* untuk panduan lengkap. 😊";
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Format integer Rupiah as human-readable currency string.
     */
    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
