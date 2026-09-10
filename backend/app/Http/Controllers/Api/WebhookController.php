<?php

namespace App\Http\Controllers\Api;

use App\Models\ProcessedMessage;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\TransactionParserService;
use App\Services\SubscriptionService;
use App\Services\PinLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
        private readonly SubscriptionService $subscriptions,
        private readonly PinLifecycleService $pins,
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

        // --- Command Routing ---
        $lowerText = strtolower($messageText);

        if (preg_match('/^set pin\s+(\d{6})$/i', $messageText, $matches)) {
            try { $code = $this->pins->setup($user, $matches[1]); return response()->json(['action'=>'REPLY_TEXT','reply_text'=>"PIN aktif. Recovery Code (simpan sekali ini): `{$code}`. Kirim ulang transaksi Anda."]); }
            catch (\RuntimeException $e) { return response()->json(['action'=>'REPLY_TEXT','reply_text'=>$e->getMessage()]); }
        }
        if (preg_match('/^reset pin\s+([A-Z0-9]{16})\s+(\d{6})$/i', $messageText, $matches)) {
            try { $this->pins->reset($user, $matches[1], $matches[2]); return response()->json(['action'=>'REPLY_TEXT','reply_text'=>'PIN berhasil direset.']); }
            catch (\RuntimeException $e) { return response()->json(['action'=>'REPLY_TEXT','reply_text'=>$e->getMessage()]); }
        }
        if (preg_match('/^(kuota|status langganan)$/i', $messageText)) {
            $status=$this->subscriptions->status($user); $tail=$status['tier']==='PREMIUM' ? 'Berlaku sampai: '.$status['expires_at']?->format('d M Y') : 'Sisa kuota gratis: '.$status['remaining'].' dari 100 pesan finansial';
            return response()->json(['action'=>'REPLY_TEXT','reply_text'=>"Status: *{$status['tier']}*\n{$tail}"]);
        }
        if (preg_match('/^(beli pro|langganan)$/i', $messageText)) {
            return response()->json(['action'=>'REPLY_TEXT','reply_text'=>'Paket Pro Rp25.000 / 30 hari. Hubungi endpoint pembelian untuk memperoleh Snap link pembayaran.']);
        }

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

        if (preg_match('/^pengeluaran hari ini$/i', $messageText, $matches)) {
            return $this->handleTransactionList(
                $user,
                'EXPENSE',
                'today'
            );
        }

        if (preg_match('/^pemasukan hari ini$/i', $messageText, $matches)) {
            return $this->handleTransactionList(
                $user,
                'INCOME',
                'today'
            );
        }

        if (preg_match('/^pengeluaran bulan ini$/i', $messageText, $matches)) {
            return $this->handleTransactionList(
                $user,
                'EXPENSE',
                'month'
            );
        }

        if (preg_match('/^pemasukan bulan ini$/i', $messageText, $matches)) {
            return $this->handleTransactionList(
                $user,
                'INCOME',
                'month'
            );
        }

        // ---------------------------------------------------------------------
        // Update Transaction
        //
        // Example:
        // ubah transaksi a82f31c2 75000 makan siang
        // ---------------------------------------------------------------------

        if (
            preg_match(
                '/^ubah transaksi\s+([a-f0-9-]+)\s+([0-9.,]+)\s+(.+)$/i',
                $messageText,
                $matches
            )
        ) {
            $transactionId = trim($matches[1]);
            $amountText     = trim($matches[2]);
            $description    = trim($matches[3]);

            $amount = $this->parseAmount($amountText);

            if ($amount <= 0) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ Nominal tidak valid. Contoh: `ubah transaksi a82f31c2 75000 makan siang`']);
            }

            return $this->handleTransactionUpdate(
                $user,
                $transactionId,
                $amount,
                $description
            );
        }

        // Delete Transaction
        if (
            preg_match(
                '/^hapus transaksi\s+([a-f0-9-]+)$/i',
                $messageText,
                $matches
            )
        ) {
            return $this->handleTransactionDelete(
                $user,
                trim($matches[1])
            );
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
            if ($isNewUser) return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $this->buildWelcomeMessage($user)]);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->buildUnrecognizedMessage($messageText),
            ]);
        }

        if ($user->pin_status !== 'ACTIVE') return response()->json(['action'=>'REPLY_TEXT','reply_text'=>'Sebelum transaksi pertama, buat PIN 6 digit: `set pin 123456`.']);
        if (!$this->subscriptions->canRecord($user)) return response()->json(['action'=>'REPLY_TEXT','reply_text'=>'Kuota gratis sudah habis. Ketik `beli pro` untuk lanjut tanpa batas.']);
        return $this->handleTransaction($user, $parsed, $messageId);
    }

    // ---------------------------------------------------------------------------
    // Command Handlers
    // ---------------------------------------------------------------------------

    private function handleTransactionList(
        User $user,
        string $type,
        ?string $period
    ): JsonResponse {
        $transactions = $this->financeService->getTransactions(
            $user,
            $type,
            $period,
            10
        );

        $typeTitle = $type === 'EXPENSE'
            ? 'Pengeluaran'
            : 'Pemasukan';

        $emoji = $type === 'EXPENSE'
            ? '💸'
            : '💰';

        if ($period === 'today') {
            $periodTitle = 'Hari Ini';
        } elseif ($period === 'month') {
            $periodTitle = now()->translatedFormat('F Y');
        } else {
            $periodTitle = 'Terbaru';
        }

        $text = "{$emoji} *{$typeTitle} - {$periodTitle}*\n";
        $text .= "━━━━━━━━━━━━━━━━━\n";

        if ($transactions->isEmpty()) {
            $text .= "📭 Belum ada " . strtolower($typeTitle) . ".\n";
            $text .= "━━━━━━━━━━━━━━━━━\n";
            $text .= "💰 Saldo: "
                . $this->formatRupiah(
                    $this->financeService->getBalance($user)
                );

            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);
        }

        foreach ($transactions as $index => $transaction) {
            $number = $index + 1;

            $category = $transaction->category
                ? $transaction->category->icon
                    . ' '
                    . $transaction->category->name
                : '📦 Lain-lain';

            $shortId = substr(
                (string) $transaction->id,
                0,
                8
            );

            $text .= "*{$number}. {$category}*\n";

            if ($transaction->description !== '') {
                $text .= "   📝 {$transaction->description}\n";
            }

            $text .= "   💵 "
                . $this->formatRupiah($transaction->amount)
                . "\n";

            $text .= "   🕐 "
                . $transaction->transaction_date->format('d/m/Y H:i')
                . "\n";

            $text .= "   🆔 `{$shortId}`\n\n";
        }

        $text .= "━━━━━━━━━━━━━━━━━\n";
        $text .= "💰 Saldo: "
            . $this->formatRupiah(
                $this->financeService->getBalance($user)
            )
            . "\n\n";

        $text .= "_Gunakan:_\n";
        $text .= "`ubah transaksi ID nominal keterangan`\n";
        $text .= "`hapus transaksi ID`";

        return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);
    }


    // =========================================================================
    // Update
    // =========================================================================

    private function handleTransactionUpdate(
        User $user,
        string $transactionId,
        int $amount,
        string $description
    ): JsonResponse {
        try {
            $transaction = $this->financeService
                ->findTransactionById(
                    $user,
                    $transactionId
                );

            if (!$transaction) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => "❌ Transaksi dengan ID `{$transactionId}` tidak ditemukan."]);
            }

            $result = $this->financeService->updateTransaction(
                $user,
                $transaction->id,
                $amount,
                $description
            );

            if (!$result) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '❌ Transaksi tidak ditemukan atau sudah tidak aktif.']);
            }

            $updated = $result['transaction'];

            $typeLabel = $updated->type === 'EXPENSE'
                ? 'Pengeluaran'
                : 'Pemasukan';

            $category = $updated->category
                ? $updated->category->icon
                    . ' '
                    . $updated->category->name
                : '📦 Lain-lain';

            $text = "✅ *Transaksi Berhasil Diubah*\n";
            $text .= "━━━━━━━━━━━━━━━━━\n";
            $text .= "📝 Keterangan: {$updated->description}\n";
            $text .= "🏷️ Kategori: {$category}\n";
            $text .= "💵 {$typeLabel}: "
                . $this->formatRupiah($updated->amount)
                . "\n";
            $text .= "━━━━━━━━━━━━━━━━━\n";
            $text .= "💰 Saldo Baru: "
                . $this->formatRupiah($result['new_balance']);

            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);

        } catch (\RuntimeException $e) {
            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ ' . $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error(
                'Webhook: Transaction update failed',
                [
                    'user' => $user->jid,
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ Gagal mengubah transaksi. Silakan coba lagi.']);
        }
    }

    // =========================================================================
    // Delete / Void
    // =========================================================================

    private function handleTransactionDelete(
        User $user,
        string $transactionId
    ): JsonResponse {
        try {
            $transaction = $this->financeService
                ->findTransactionById(
                    $user,
                    $transactionId
                );

            if (!$transaction) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => "❌ Transaksi dengan ID `{$transactionId}` tidak ditemukan."]);
            }

            $result = $this->financeService->voidTransaction(
                $user,
                $transaction->id
            );

            if (!$result) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '❌ Transaksi tidak ditemukan atau sudah dihapus.']);
            }

            $voided = $result['voided'];

            $typeLabel = $voided->type === 'EXPENSE'
                ? 'Pengeluaran'
                : 'Pemasukan';

            $text = "🗑️ *Transaksi Berhasil Dihapus*\n";
            $text .= "━━━━━━━━━━━━━━━━━\n";

            if ($voided->description !== '') {
                $text .= "📝 {$voided->description}\n";
            }

            $text .= "💵 {$typeLabel}: "
                . $this->formatRupiah($voided->amount)
                . "\n";

            $text .= "━━━━━━━━━━━━━━━━━\n";
            $text .= "💰 Saldo Baru: "
                . $this->formatRupiah($result['new_balance']);

            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);

        } catch (\RuntimeException $e) {
            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ ' . $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error(
                'Webhook: Transaction delete failed',
                [
                    'user' => $user->jid,
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ Gagal menghapus transaksi. Silakan coba lagi.']);
        }
    }

    // =========================================================================
    // Balance
    // =========================================================================

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

    private function handleTransaction(User $user, array $parsed, string $messageId): JsonResponse
    {
        // Match category from hint
        $category = $this->financeService->matchCategory($user, $parsed['category_hint'], $parsed['type']);

        try {
            [$transaction, $remaining] = DB::transaction(function () use ($user, $parsed, $category, $messageId) {
                $transaction = $this->financeService->recordTransaction($user, [
                    'type' => $parsed['type'], 'amount' => $parsed['amount'],
                    'description' => $parsed['description'], 'category_id' => $category?->id,
                ]);
                return [$transaction, $this->subscriptions->consume($user, $messageId)];
            });

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
            if ($user->tier === 'FREE' && $remaining <= 10) $text .= "\nSisa kuota gratis: {$remaining} pesan. Ketik `beli pro` untuk tanpa batas.";

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

        return "👋 Halo *{$name}*! Selamat datang "
            . "di *WA Finance Bot* 💰\n\n"
            . "Bot ini membantu kamu catat keuangan "
            . "harian langsung dari WhatsApp!\n\n"
            . "*Cara Pakai:*\n"
            . "  📤 _keluar 25000 makan siang_\n"
            . "  📥 _masuk 500000 gaji_\n"
            . "  💰 _saldo_\n"
            . "  📤 _pengeluaran hari ini_\n"
            . "  📤 _pengeluaran bulan ini_\n"
            . "  📥 _pemasukan hari ini_\n"
            . "  📥 _pemasukan bulan ini_\n"
            . "  📊 _rekap bulan ini_\n"
            . "  📋 _export excel_\n"
            . "  ↩️ _batal_\n\n"
            . "Ketik *bantuan* untuk panduan lengkap.\n\n"
            . "_Yuk mulai catat! 🚀_";
    }

    private function buildHelpMessage(): string
    {
        return "📚 *Panduan WA Finance Bot*\n\n"

            . "*Catat Pengeluaran:*\n"
            . "  • `keluar 25000 makan siang`\n"
            . "  • `beli bensin 50rb`\n"
            . "  • `bayar listrik 150.000`\n"
            . "  • `-35k parkir`\n\n"

            . "*Catat Pemasukan:*\n"
            . "  • `masuk 500000 gaji`\n"
            . "  • `terima 250k bonus`\n"
            . "  • `+1.5jt freelance`\n\n"

            . "*Lihat Transaksi:*\n"
            . "  • `pengeluaran hari ini`\n"
            . "  • `pemasukan hari ini`\n"
            . "  • `pengeluaran bulan ini`\n"
            . "  • `pemasukan bulan ini`\n\n"

            . "*Saldo & Laporan:*\n"
            . "  • `saldo`\n"
            . "  • `rekap hari ini`\n"
            . "  • `rekap bulan ini`\n\n"

            . "*Kelola Transaksi:*\n"
            . "  • `ubah transaksi ID nominal keterangan`\n"
            . "  • `hapus transaksi ID`\n"
            . "  • `batal`\n\n"

            . "*Lainnya:*\n"
            . "  • `export excel`\n"
            . "  • `kategori`\n"
            . "  • `tambah kategori <nama>`\n\n"

            . "_Format nominal: "
            . "25000, 25.000, 25k, 25rb, 1.5jt_ 💡";
    }

    private function buildUnrecognizedMessage(
        string $message
    ): string {
        return "🤔 Maaf, saya tidak mengerti "
            . "maksud pesan:\n"
            . "\"_{$message}_\"\n\n"

            . "*Format transaksi:*\n"
            . "  📤 `keluar 25000 makan siang`\n"
            . "  📥 `masuk 500000 gaji`\n\n"

            . "*Lihat transaksi:*\n"
            . "  📤 `pengeluaran hari ini`\n"
            . "  📤 `pengeluaran bulan ini`\n"
            . "  📥 `pemasukan hari ini`\n"
            . "  📥 `pemasukan bulan ini`\n\n"

            . "Atau ketik *bantuan* untuk "
            . "panduan lengkap. 😊";
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

        private function parseAmount(string $value): int
    {
        $value = strtolower(trim($value));
        $value = str_replace(' ', '', $value);

        if (preg_match('/^([\d.,]+)(jt|juta)$/i', $value, $m)) {
            return (int) round(
                (float) str_replace(',', '.', $m[1])
                * 1_000_000
            );
        }

        if (preg_match('/^([\d.,]+)(rb|ribu|k)$/i', $value, $m)) {
            return (int) round(
                (float) str_replace(',', '.', $m[1])
                * 1_000
            );
        }

        $numeric = preg_replace('/[^\d]/', '', $value);

        return (int) ($numeric ?: 0);
    }

    /**
     * Format integer Rupiah as human-readable currency string.
     */
    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
