<?php

namespace App\Http\Controllers\Api;

use App\Models\BotMenu;
use App\Models\Budget;
use App\Models\FinancialGoal;
use App\Models\ProcessedMessage;
use App\Models\RecurringSchedule;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BudgetService;
use App\Services\FinanceService;
use App\Services\InsightsService;
use App\Services\RecurringService;
use App\Services\TransactionParserService;
use App\Services\SubscriptionService;
use App\Services\PinLifecycleService;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use Illuminate\Support\Str;

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
        private readonly BudgetService $budgetService,
        private readonly RecurringService $recurringService,
        private readonly InsightsService $insightsService,
        private readonly MidtransService $midtrans,
    ) {}

    /**
     * GET /api/webhook/whatsapp
     * Meta Cloud API Webhook handshake verification.
     */
    public function verifyMetaWebhook(Request $request)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $expectedToken = config('services.whatsapp.verify_token', env('WHATSAPP_VERIFY_TOKEN', 'test123'));

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::info("Meta Webhook: Verification challenge passed successfully.");
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning("Meta Webhook: Verification challenge failed.", [
            'mode'           => $mode,
            'received_token' => $token,
            'expected_token' => $expectedToken,
        ]);
        return response('Forbidden', 403);
    }

    /**
     * POST /api/webhook/whatsapp
     *
     * Entry point for incoming WhatsApp messages from Baileys sidecar or Meta Cloud API.
     */
    public function handle(Request $request): JsonResponse
    {
        // 0. Support Meta WhatsApp Cloud API webhooks
        if ($request->input('object') === 'whatsapp_business_account' || $request->hasHeader('X-Hub-Signature-256')) {
            return $this->handleMetaWebhook($request);
        }

        // Validate webhook secret header (FR-012 security for Baileys gateway)
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        if ($secret && $request->header('X-Gateway-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'message_id'    => 'required|string',
            'from_jid'      => 'required|string',
            'push_name'     => 'nullable|string',
            'message_text'  => 'required|string',
            'timestamp'     => 'nullable|integer',
            'is_voice_note' => 'nullable|boolean',
        ]);

        $isVoice = (bool) ($validated['is_voice_note'] ?? false);

        // Asynchronous ingestion option for Baileys Gateway (Fase 3 Decoupled Webhook)
        if ($request->header('X-Async-Processing') === 'true' || config('services.whatsapp.async_baileys', env('ASYNC_BAILEYS_WEBHOOK', false))) {
            ProcessIncomingWhatsAppMessage::dispatch(
                'baileys',
                $validated['message_id'],
                $validated['from_jid'],
                $validated['push_name'] ?? '',
                trim($validated['message_text']),
                null,
                $isVoice
            );

            return response()->json([
                'status'     => 'QUEUED',
                'action'     => 'QUEUED',
                'message_id' => $validated['message_id'],
            ], 202);
        }

        $res = $this->processMessage(
            $validated['message_id'],
            $validated['from_jid'],
            $validated['push_name'] ?? '',
            trim($validated['message_text'])
        );

        if ($isVoice) {
            $data = $res->getData(true);
            if (!empty($data['reply_text'])) {
                $data['reply_text'] = "🎤 _\"{$validated['message_text']}\"_\n\n" . $data['reply_text'];
                return response()->json($data, $res->getStatusCode());
            }
        }

        return $res;
    }

    /**
     * Handle incoming payload from Meta WhatsApp Business Cloud API.
     * Decoupled asynchronous ingestion responding in <10ms (Fase 2 & Fase 3).
     */
    protected function handleMetaWebhook(Request $request): JsonResponse
    {
        Log::info("Meta Webhook Received", ['object' => $request->input('object'), 'entries_count' => count($request->input('entry', []))]);

        // Verify X-Hub-Signature-256 if Meta app secret is configured (Fase 2 security)
        $signature = $request->header('X-Hub-Signature-256');
        $appSecret = config('services.whatsapp.app_secret', env('WHATSAPP_APP_SECRET', env('META_APP_SECRET')));
        if ($signature && $appSecret) {
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
            if (!hash_equals($expected, $signature)) {
                Log::warning("Meta Webhook: Invalid X-Hub-Signature-256 signature.");
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $entries = $request->input('entry', []);
        $queuedCount = 0;

        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $contacts = $value['contacts'] ?? [];
                $contactName = $contacts[0]['profile']['name'] ?? '';

                foreach ($value['messages'] ?? [] as $msg) {
                    $fromPhone = $msg['from'] ?? null;
                    if (!$fromPhone) continue;

                    $fromJid = $fromPhone . '@s.whatsapp.net';
                    $messageId = $msg['id'] ?? Str::uuid()->toString();
                    $messageType = $msg['type'] ?? 'text';
                    $messageText = '';
                    $isVoice = false;
                    $mediaId = null;

                    if ($messageType === 'text') {
                        $messageText = trim($msg['text']['body'] ?? '');
                    } elseif ($messageType === 'interactive') {
                        $interactive = $msg['interactive'] ?? [];
                        if (($interactive['type'] ?? '') === 'button_reply') {
                            $messageText = trim($interactive['button_reply']['id'] ?? $interactive['button_reply']['title'] ?? '');
                        } elseif (($interactive['type'] ?? '') === 'list_reply') {
                            $messageText = trim($interactive['list_reply']['id'] ?? $interactive['list_reply']['title'] ?? '');
                        }
                    } elseif ($messageType === 'audio') {
                        $isVoice = true;
                        $mediaId = $msg['audio']['id'] ?? null;
                    }

                    if ($messageText === '' && !$mediaId) {
                        continue;
                    }

                    // Dispatch async job to Redis Queue for high-throughput decoupling (<10ms HTTP response)
                    ProcessIncomingWhatsAppMessage::dispatch(
                        'meta',
                        $messageId,
                        $fromJid,
                        $contactName,
                        $messageText,
                        $fromPhone,
                        $isVoice,
                        $mediaId
                    );
                    $queuedCount++;
                }
            }
        }

        return response()->json([
            'status' => 'EVENT_RECEIVED',
            'queued' => true,
            'count'  => $queuedCount,
        ], 200);
    }

    /**
     * Send outbound message back to user via Meta WhatsApp Cloud API.
     */
    public function sendMetaReply(string $toPhone, string $text): void
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id', env('WHATSAPP_PHONE_NUMBER_ID'));
        $accessToken = config('services.whatsapp.access_token', env('WHATSAPP_ACCESS_TOKEN'));
        $version = config('services.whatsapp.api_version', env('WHATSAPP_API_VERSION', 'v21.0'));

        if (!$phoneNumberId || !$accessToken) {
            Log::warning("Meta WhatsApp Cloud API: Missing Phone Number ID or Access Token in .env");
            return;
        }

        try {
            $resp = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $toPhone,
                    'type'              => 'text',
                    'text'              => [
                        'preview_url' => false,
                        'body'        => $text,
                    ],
                ]);

            if ($resp->failed()) {
                Log::error("Meta WhatsApp Cloud API Send Message Failed", [
                    'to'     => $toPhone,
                    'status' => $resp->status(),
                    'body'   => $resp->json(),
                ]);
            } else {
                Log::info("Meta WhatsApp Cloud API message sent to {$toPhone}");
            }
        } catch (\Throwable $e) {
            Log::error("Meta WhatsApp Cloud API Exception: " . $e->getMessage());
        }
    }

    /**
     * Send outbound interactive message (buttons/list) via Meta WhatsApp Cloud API.
     */
    public function sendMetaInteractive(string $toPhone, array $interactive): bool
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id', env('WHATSAPP_PHONE_NUMBER_ID'));
        $accessToken = config('services.whatsapp.access_token', env('WHATSAPP_ACCESS_TOKEN'));
        $version = config('services.whatsapp.api_version', env('WHATSAPP_API_VERSION', 'v21.0'));

        if (!$phoneNumberId || !$accessToken) {
            Log::warning("Meta WhatsApp Cloud API: Missing Phone Number ID or Access Token in .env");
            return false;
        }

        try {
            $resp = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $toPhone,
                    'type'              => 'interactive',
                    'interactive'       => $interactive,
                ]);

            if ($resp->failed()) {
                Log::error("Meta WhatsApp Cloud API Send Interactive Failed", [
                    'to'     => $toPhone,
                    'status' => $resp->status(),
                    'body'   => $resp->json(),
                ]);
                return false;
            }

            Log::info("Meta WhatsApp Cloud API interactive message sent to {$toPhone}");
            return true;
        } catch (\Throwable $e) {
            Log::error("Meta WhatsApp Cloud API Interactive Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process message through the financial engine and command router.
     */
    public function processMessage(string $messageId, string $fromJid, string $pushName, string $messageText): JsonResponse
    {

        // --- Payload Length Protection (Anti-ReDoS / Memory Spike) ---
        if (mb_strlen($messageText) > 2000) {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => '⚠️ Pesan terlalu panjang (maksimal 2.000 karakter). Mohon kirimkan catatan transaksi yang lebih ringkas.',
            ]);
        }

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

        // --- Blocked User Check ---
        if (! $user->is_active) {
            $reason = $user->blocked_reason ? "\n*Alasan:* {$user->blocked_reason}" : '';
            return response()->json([
                'action' => 'REPLY_TEXT',
                'reply_text' => "⛔ *Akses Dinonaktifkan*\nNomor WhatsApp Anda telah diblokir dari layanan ini.{$reason}\n\nSilakan hubungi administrator jika Anda merasa ini adalah kekeliruan.",
            ]);
        }

        // --- Command Routing ---
        $lowerText = strtolower(trim($messageText));
        $userName = $user->display_name ?: 'Kak';

        // 1. Greetings (Halo, Hai, Assalamualaikum, Selamat Pagi/Siang/Malam, P, Ping)
        if (preg_match('/^(?:halo+|hai+|hey+|hei+|hello+|hi+|p+|ping|assalam(?:u[\'\’]?alaikum)?|samlikum|selamat\s+(?:pagi|siang|sore|malam)|pagi|siang|sore|malam)$/i', $lowerText)) {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "👋 Halo *{$userName}*! Selamat datang di *HematWoy* 💰\n\n"
                    . "Ada pengeluaran atau pemasukan yang mau dicatat hari ini? 😊\n\n"
                    . "💡 *Pilihan Cepat:*\n"
                    . "• Catat: `keluar 25rb makan` atau `+500rb gaji`\n"
                    . "• Cek Saldo: `saldo`\n"
                    . "• Dashboard Web: `web`\n"
                    . "• Menu Lengkap: `menu`",
            ]);
        }

        // 2. Gratitude (Terima kasih, makasih, thanks, etc.)
        if (preg_match('/^(?:terima\s*kasih(?:\s+banyak)?|makasih+(?:\s+banyak)?|makasi+|thanks+(?:\s+a\s+lot)?|tq|tengkyu|syukron|maturnuwun)(?:\s+(?:ya|kak|min|bos))?$/i', $lowerText)) {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "Sama-sama, *{$userName}*! Senang bisa membantu merapikan keuanganmu. 😊\n\nKetik `saldo` untuk cek keuangan terkini, atau `menu` untuk panduan lengkap.",
            ]);
        }

        // 3. Affirmation (Oke, Siap, Mantap, Keren, Sip)
        if (preg_match('/^(?:(?:oke+|ok+|sip+|siap+|mantap+|keren|yoi|mantul)(?:\s+(?:oke+|ok+|sip+|siap+|mantap+|keren|yoi|mantul|kak|min|bos|ya))?)$/i', $lowerText)) {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "Siap! 👍 Langsung ketik saja kapan pun ada transaksi baru ya (misal: `keluar 15rb kopi`).",
            ]);
        }

        // 4. Web Dashboard Link
        if (preg_match('/^(?:web|dashboard|link\s+web|buka\s+web|aplikasi|app|portal)$/i', $lowerText)) {
            $webUrl = url('/');
            $phoneInfo = $user->phone_number
                ? "Nomor HP tertaut: *+{$user->phone_number}*"
                : "Belum menautkan nomor HP? Ketik: `tautkan 08xxxxxxxxxx`";

            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "🌐 *Web App Dashboard — HematWoy*\n\n"
                    . "Pantau keuanganmu dengan grafik visual, pie chart, dan kelola dompet di browser:\n"
                    . "👉 {$webUrl}\n\n"
                    . "📱 *Cara Login:*\n"
                    . "1. Buka link di atas.\n"
                    . "2. Masukkan nomor HP Anda yang tertaut.\n"
                    . "3. Masukkan kode OTP yang dikirimkan ke WhatsApp ini.\n\n"
                    . "{$phoneInfo}",
            ]);
        }

        if (preg_match('/^(?:set|buat|bikin|atur)\s+pin\s+(\S{6,64})$/i', $messageText, $matches)) {
            try {
                $code = $this->pins->setup($user, $matches[1]);
                $reply = "✅ *Master PIN / Kata Sandi Aktif!* 🔐\n\n"
                    . "🔑 *Recovery Code Darurat:* `{$code}`\n\n"
                    . "⚠️ *PENTING:* Simpan Recovery Code di atas di tempat aman (catatan pribadi). Kode ini adalah satu-satunya kunci darurat jika kamu lupa PIN.\n\n"
                    . "Sekarang akunmu sudah siap mencatat keuangan! Silakan kirim ulang transaksi atau ketik `menu` untuk panduan. 🚀";
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $reply]);
            } catch (\RuntimeException $e) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $e->getMessage()]);
            }
        }
        if (preg_match('/^reset pin\s+([A-Z0-9]{16})\s+(\S{6,64})$/i', $messageText, $matches)) {
            try {
                $this->pins->reset($user, $matches[1], $matches[2]);
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => "✅ *PIN Berhasil Diperbarui!* 🔐\n\nPIN baru kamu sudah aktif dan data keuanganmu terlindungi kembali. Silakan lanjutkan pencatatan transaksi! 😊",
                ]);
            } catch (\RuntimeException $e) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ ' . $e->getMessage()]);
            }
        }
        // 1. Quota & Subscription Status Check (Must be evaluated before purchase intent so "status langganan" or "cek status kuota" shows quota)
        if (preg_match('/^(?:kuota|status\s*(?:langganan|paket|kuota)?|sisa\s+kuota|cek\s+(?:status\s*)?(?:kuota|paket|langganan)|paket\s+saya)$/i', $lowerText)) {
            $status = $this->subscriptions->status($user);
            $isPro = $status['tier'] === 'PREMIUM';

            if ($isPro) {
                $expiry = $status['expires_at'] ? $status['expires_at']->translatedFormat('d F Y') : '-';
                $card  = "⭐ *Status Langganan Anda*\n";
                $card .= "───────────────────────\n";
                $card .= "🏷️ Status Paket : *HEMATWOY PRO (Aktif)*\n";
                $card .= "📅 Berlaku Sampai : *{$expiry}*\n";
                $card .= "💬 Kuota Pesan : *Unlimited (Tanpa Batas)*\n\n";
                $card .= "Terima kasih telah menjadi pelanggan setia HematWoy! 🚀";
                $interactive = null;
            } else {
                $limit = $status['free_limit'];
                $used = $status['used'];
                $percentage = $limit > 0 ? min(100, max(0, (int) round(($used / $limit) * 100))) : 100;
                $barFilled = (int) round($percentage / 5);
                $progressBar = str_repeat('█', $barFilled) . str_repeat('░', 20 - $barFilled);

                $card  = "📊 *Status Kuota Finansial*\n";
                $card .= "───────────────────────\n";
                $card .= "🏷️ Paket Saat Ini : *GRATIS (Freemium)*\n";
                $card .= "💬 Sisa Kuota     : *{$status['remaining']}* dari {$limit} pesan finansial gratis\n";
                $card .= "📈 Penggunaan     : {$progressBar} {$percentage}%\n\n";
                $card .= "💡 *Mau catat tanpa batas & kelola banyak dompet?*\n";
                $card .= "Ketik: `langganan` untuk upgrade ke HematWoy Pro! ⭐";

                $plans = SubscriptionPlan::active()->get();
                $quotaButtons = [];
                if ($plans->isNotEmpty()) {
                    foreach ($plans->take(2) as $p) {
                        $quotaButtons[] = [
                            'type' => 'reply',
                            'reply' => [
                                'id' => "langganan {$p->code}",
                                'title' => "{$p->name} ({$p->short_price})",
                            ],
                        ];
                    }
                } else {
                    $quotaButtons = [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'langganan bulanan',
                                'title' => 'Pro Bulanan (25rb)',
                            ],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'langganan tahunan',
                                'title' => 'Pro 1 Tahun (199rb)',
                            ],
                        ],
                    ];
                }

                $interactive = [
                    'type' => 'button',
                    'header' => [
                        'type' => 'text',
                        'text' => '📊 Status Kuota Anda',
                    ],
                    'body' => [
                        'text' => $card,
                    ],
                    'footer' => [
                        'text' => 'Klik tombol untuk upgrade akun',
                    ],
                    'action' => [
                        'buttons' => $quotaButtons,
                    ],
                ];
            }

            return response()->json([
                'action'      => 'REPLY_TEXT',
                'reply_text'  => $card,
                'interactive' => $interactive,
            ]);
        }

        // 2. Subscription & Pricing — High-Converting In-Chat & Web-Integrated Checkout
        $isFinancialInput = (bool) preg_match('/^(?:keluar|masuk|\+|\-|\d)/i', $lowerText);
        $isGoalCommand = (bool) preg_match('/^(?:daftar\s+target|target\s+tabungan|daftar\s+goals?|buat\s+target|tabung\s+\d+)/i', $lowerText);
        $isRecurringSchedule = (bool) preg_match('/^(?:daftar\s+langganan|hapus\s+langganan|(?:langganan|buat\s+langganan)\s+.+?\s+[0-9.,]+.*setiap\s+tanggal)/i', $lowerText);
        $isCategoryOrWallet = (bool) preg_match('/^(?:daftar\s+(?:dompet|kategori)|tambah\s+(?:dompet|kategori))/i', $lowerText);

        $isSubscriptionIntent = ! $isFinancialInput && ! $isRecurringSchedule && ! $isGoalCommand && ! $isCategoryOrWallet && (
            preg_match('/^(?:halo\s+hematwoy[,.!]?\s*)?(?:saya\s+)?(?:mau|ingin|pengen|bisa)?\s*(?:beli|upgrade|ambil|daftar|berlangganan|langganan|perpanjang)\s+(?:ke\s+)?(?:hematwoy\s*)?(?:paket\s*)?(?:pro|tahunan|bulanan|premium)/i', $lowerText)
            || preg_match('/\b(?:langganan|berlangganan|upgrade|perpanjang)\b/i', $lowerText)
            || preg_match('/\b(?:paket\s+(?:pro|tahunan|bulanan)|premium)\b/i', $lowerText)
        );

        if ($isSubscriptionIntent) {
            $plans = SubscriptionPlan::active()->get();
            $isAnnual = (bool) preg_match('/(?:tahun|annual|199)/i', $lowerText);
            $isAlreadyPro = $user->hasActivePremium();
            $currentExpiry = $user->subscription_expires_at ? $user->subscription_expires_at->translatedFormat('d F Y') : null;

            $selectedPlan = null;
            if ($plans->isNotEmpty()) {
                if ($isAnnual) {
                    $selectedPlan = $plans->firstWhere('code', 'annual') ?? $plans->first();
                } else {
                    $selectedPlan = $plans->first(function ($p) use ($lowerText) {
                        return str_contains($lowerText, $p->code) || str_contains($lowerText, strtolower($p->name));
                    }) ?? $plans->firstWhere('code', 'monthly') ?? $plans->first();
                }
            }

            $amount = $selectedPlan ? (int) $selectedPlan->price : ($isAnnual ? MidtransService::ANNUAL_PRICE : MidtransService::PRICE);
            $planNamePrefix = $isAlreadyPro ? 'Perpanjang ' : '';
            $planName = $selectedPlan ? "{$planNamePrefix}HematWoy {$selectedPlan->name}" : ($isAnnual ? "{$planNamePrefix}HematWoy Pro 1 Tahun" : "{$planNamePrefix}HematWoy Pro 30 Hari");

            // Resolve dynamic public base URL for public checkout links (e.g. Ngrok / Custom Domain)
            $req = request();
            $requestHost = $req ? ($req->header('X-Forwarded-Host') ?: $req->getHost()) : null;
            $requestScheme = $req ? ($req->header('X-Forwarded-Proto') ?: $req->getScheme()) : null;
            $requestPort = $req ? ($req->header('X-Forwarded-Port') ?: $req->getPort()) : null;

            $portSuffix = '';
            if ($requestPort && !in_array((int) $requestPort, [80, 443], true) && $requestHost && !str_contains($requestHost, ':')) {
                $portSuffix = ":{$requestPort}";
            }
            $baseUrl = ($requestHost && $requestScheme) ? "{$requestScheme}://{$requestHost}{$portSuffix}" : url('/');

            $order = $this->midtrans->createOrder($user, $amount, $planName, $baseUrl);
            $payUrl = $order->payment_url ?: "{$baseUrl}/pay/{$order->order_id}";

            $formattedAmount = 'Rp' . number_format($amount, 0, ',', '.');
            $featuresList = ($selectedPlan && !empty($selectedPlan->features)) ? $selectedPlan->features : [
                'Catat transaksi tanpa batas kuota (Unlimited)',
                'Multi-Dompet (BCA, Mandiri, Cash, GoPay, OVO)',
                'Download Laporan Excel (.xlsx) instan',
                'Plafon Anggaran (Budget) & Peringatan Boncos',
                'Evaluasi & Analisis Pengeluaran Pintar',
            ];

            if ($isAlreadyPro) {
                $headerTitle = $isAnnual ? 'Perpanjang HematWoy Pro 1 Tahun' : 'Perpanjang HematWoy Pro 30 Hari';
                $text  = "⭐ *{$headerTitle}*\n";
                $text .= "───────────────────────\n";
                $text .= "Status paket Anda saat ini : *HEMATWOY PRO (Aktif)*\n";
                $text .= "📅 Masa aktif s/d : *{$currentExpiry}*\n\n";
                $text .= "💡 *Info Perpanjangan:*\n";
                $text .= "Masa aktif baru akan *langsung ditambahkan (diakumulasikan)* ke tanggal berakhir saat ini.\n";
            } else {
                $headerTitle = $isAnnual ? 'HematWoy Pro 1 Tahun — Akses Tanpa Batas' : 'HematWoy Pro — Akses Tanpa Batas';
                $text  = "⭐ *{$headerTitle}*\n";
                $text .= "───────────────────────\n";
                $text .= "Kelola seluruh keuanganmu dengan fitur premium tanpa batasan:\n\n";
                $text .= "✨ *Fitur Spesial Pro:*\n";
                foreach ($featuresList as $feat) {
                    $text .= "• 🚀 {$feat}\n";
                }
            }

            if ($plans->isNotEmpty()) {
                $text .= "\n💳 *Pilihan Paket " . ($isAlreadyPro ? 'Perpanjangan' : 'Langganan') . ":*\n";
                foreach ($plans as $p) {
                    $discountNote = $p->discount_percentage > 0 ? " _(Hemat {$p->discount_percentage}%)_" : '';
                    $text .= "• {$p->name} : *{$p->formatted_price}* / " . ($p->duration_days >= 365 ? 'tahun' : "{$p->duration_days} hari") . "{$discountNote}\n";
                }
            } else {
                $text .= "\n💳 *Pilihan Paket " . ($isAlreadyPro ? 'Perpanjangan' : 'Langganan') . ":*\n";
                $text .= "• Paket Bulanan : *Rp25.000* / 30 hari\n";
                $text .= "• Paket Tahunan : *Rp199.000* / tahun _(Hemat 35%)_\n";
            }

            $text .= "\n👉 *Klik link berikut untuk pembayaran " . ($isAlreadyPro ? 'perpanjangan ' : '') . "{$planName} ({$formattedAmount}):*\n";
            $text .= "{$payUrl}\n\n";

            if ($plans->isNotEmpty()) {
                foreach ($plans as $altPlan) {
                    if (!$selectedPlan || $altPlan->code !== $selectedPlan->code) {
                        $text .= "_💡 Mau {$altPlan->name} ({$altPlan->formatted_price})? Ketik: `langganan {$altPlan->code}`_\n";
                    }
                }
            } else {
                $text .= $isAnnual
                    ? "_💡 Mau Paket Bulanan (Rp25.000 / 30 hari)? Ketik: `langganan bulanan`_\n"
                    : "_💡 Mau langsung ambil Paket Tahunan Rp199.000? Ketik: `langganan tahunan`_\n";
            }
            $text .= "_Akun Pro akan otomatis aktif seketika setelah pembayaran berhasil diproses! 🚀_";

            $replyButtons = [];
            if ($plans->isNotEmpty()) {
                foreach ($plans->take(2) as $p) {
                    $btnLabel = $isAlreadyPro ? "Perpanjang {$p->name}" : "{$p->name} ({$p->short_price})";
                    $replyButtons[] = [
                        'type' => 'reply',
                        'reply' => [
                            'id' => "langganan {$p->code}",
                            'title' => mb_substr($btnLabel, 0, 20),
                        ],
                    ];
                }
            } else {
                $replyButtons = [
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'langganan bulanan',
                            'title' => $isAlreadyPro ? 'Perpanjang Bulanan' : 'Pro Bulanan (25rb)',
                        ],
                    ],
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'langganan tahunan',
                            'title' => $isAlreadyPro ? 'Perpanjang Tahunan' : 'Pro 1 Tahun (199rb)',
                        ],
                    ],
                ];
            }
            $replyButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => 'status langganan',
                    'title' => 'Cek Status Kuota',
                ],
            ];

            $interactive = [
                'type' => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => "⭐ {$planName}",
                ],
                'body' => [
                    'text' => "Tingkatkan ke *{$planName}* untuk akses tanpa batas!\n\n"
                        . "✨ Akses penuh Web Dashboard, Unlimited Catat, Multi-Dompet & Ekspor Excel.\n\n"
                        . "👉 Link Bayar ({$formattedAmount}):\n{$payUrl}\n\n"
                        . "Atau klik tombol pilihan di bawah:",
                ],
                'footer' => [
                    'text' => 'Pilih paket di bawah untuk ganti opsi',
                ],
                'action' => [
                    'buttons' => $replyButtons,
                ],
            ];

            return response()->json([
                'action'      => 'REPLY_TEXT',
                'reply_text'  => $text,
                'interactive' => $interactive,
            ]);
        }

        // Link phone command: "tautkan 08xxxx" or "nohp 08xxxx"
        if (preg_match('/^(?:tautkan|nohp|nomor)\s+(?:hp\s+)?(\+?0?8\d{8,12})$/i', $messageText, $matches)) {
            $rawPhone = preg_replace('/\D/', '', $matches[1]);
            if (str_starts_with($rawPhone, '0')) {
                $rawPhone = '62' . substr($rawPhone, 1);
            }
            $user->update(['phone_number' => $rawPhone]);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "📱 *Nomor HP Berhasil Ditautkan!*\n\nNomor: *+{$rawPhone}*\n\nAkun WhatsApp Anda sekarang telah terhubung dengan Web App. Anda dapat login ke Web App menggunakan nomor ini dengan kode OTP.",
            ]);
        }

        // Balance inquiry & Wallets (Typo-tolerant & Natural phrasing)
        if (preg_match('/^(?:saldo+|saldu|cek saldo|dompet|list dompet|daftar dompet|rekening|sisa saldo|saldo ku|cek dompet|uang(?:ku)?|sisa uang(?:ku)?|duit(?:ku)?|cek duit|cek uang|total saldo|total uang|sisa dana|uang(?:ku)? (?:ada|sisa|tinggal) berapa|sisa (?:uang|saldo|dana)(?:ku)?(?: berapa)?|(?:uang|duit|saldo)(?:ku)? (?:tinggal |sisa )?berapa)$/i', $lowerText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleBalanceInquiry($user);
        }

        // Add wallet: "tambah dompet <nama> [saldo <nominal>]"
        if ($addWallet = $this->parser->parseAddWallet($messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            try {
                $wallet = $this->financeService->addWallet($user, $addWallet['name'], $addWallet['initial_balance']);
                $this->consumeFinancialQuota($user, $messageId);
                $initText = $addWallet['initial_balance'] > 0
                    ? ' (Saldo Awal: ' . $this->formatRupiah($addWallet['initial_balance']) . ')'
                    : '';
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => "👛 *Dompet Baru Ditambahkan*\nNama: *{$wallet->name}*{$initText}\n\nKetik `saldo` untuk melihat rincian dompet.",
                ]);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => '⚠️ ' . $e->getMessage(),
                ]);
            }
        }

        // Rekap hari ini (Typo-tolerant: rekap hr ini, rekap harian)
        if (preg_match('/^rekap\s+(?:hari ini|hr ini|harii ini|harian)$/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleDailySummary($user);
        }

        // Rekap bulan ini (Typo-tolerant: rekap bln ini, rekap bulanan)
        if (preg_match('/^rekap(?:\s+(?:bulan ini|bln ini|blan ini|bulanan))?$/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleMonthlySummary($user);
        }

        // Inter-wallet transfer: "transfer <nominal> dari <asal> ke <tujuan>"
        if ($transferData = $this->parser->parseTransfer($messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $fromWallet = $this->financeService->findWalletByName($user, $transferData['from_wallet']);
            $toWallet   = $this->financeService->findWalletByName($user, $transferData['to_wallet']);

            if (!$fromWallet) {
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => "❌ Dompet asal '{$transferData['from_wallet']}' tidak ditemukan. Ketik `saldo` untuk cek daftar dompet.",
                ]);
            }

            if (!$toWallet) {
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => "❌ Dompet tujuan '{$transferData['to_wallet']}' tidak ditemukan. Ketik `saldo` untuk cek daftar dompet.",
                ]);
            }

            try {
                $tx = $this->financeService->transferFunds(
                    $user,
                    $fromWallet->id,
                    $toWallet->id,
                    $transferData['amount']
                );

                $this->consumeFinancialQuota($user, $messageId, 1);
                $fromWallet->refresh();
                $toWallet->refresh();
                $user->refresh();

                $card  = "🔄 *Transfer Berhasil*\n";
                $card .= "Nominal: " . $this->formatRupiah($transferData['amount']) . "\n";
                $card .= "Dari: {$fromWallet->name} (Sisa: " . $this->formatRupiah($fromWallet->balance) . ")\n";
                $card .= "Ke: {$toWallet->name} (Sisa: " . $this->formatRupiah($toWallet->balance) . ")\n";
                $card .= "Total Saldo Tetap: " . $this->formatRupiah($user->current_balance) . "\n\n";
                $card .= "_Ketik \"batal\" jika ingin membatalkan transfer ini._";

                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => $card,
                ]);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => '⚠️ ' . $e->getMessage(),
                ]);
            }
        }

        // Recurring schedules / Langganan
        if ($recData = $this->parser->parseRecurringSchedule($messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $wallet = null;
            if (!empty($recData['wallet_name'])) {
                $wallet = $this->financeService->findWalletByName($user, $recData['wallet_name']);
            }
            if (!$wallet) {
                $wallet = $this->financeService->getOrCreateDefaultWallet($user);
            }

            $schedule = $this->recurringService->createSchedule($user, [
                'description'  => $recData['description'],
                'amount'       => $recData['amount'],
                'day_of_month' => $recData['day_of_month'],
                'wallet_id'    => $wallet->id,
                'type'         => $recData['type'],
            ]);

            $this->consumeFinancialQuota($user, $messageId);
            $amountFmt = $this->formatRupiah($schedule->amount);
            $nextFmt   = \Carbon\Carbon::parse($schedule->next_run_date)->format('d M Y');

            $card  = "⏰ *Transaksi Rutin Terjadwal*\n";
            $card .= "Nama: {$schedule->description}\n";
            $card .= "Nominal: {$amountFmt}\n";
            $card .= "Jadwal: Tanggal {$schedule->day_of_month} setiap bulan\n";
            $card .= "Dompet: {$wallet->name}\n";
            $card .= "Eksekusi Berikutnya: {$nextFmt}";

            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $card,
            ]);
        }

        if (preg_match('/^(?:daftar|list)\s+langganan$/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->recurringService->renderSchedulesCard($user),
            ]);
        }

        if (preg_match('/^hapus\s+langganan\s+(.+)$/i', $messageText, $matches)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $canceled = $this->recurringService->cancelSchedule($user, trim($matches[1]));
            if ($canceled) {
                $this->consumeFinancialQuota($user, $messageId);
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => "✅ Transaksi rutin *{$canceled->description}* berhasil dibatalkan.",
                ]);
            }
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "❌ Transaksi rutin '{$matches[1]}' tidak ditemukan.",
            ]);
        }

        // Financial Goals (Target Tabungan)
        if ($goalData = $this->parser->parseCreateGoal($messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $goal = $this->financeService->createGoal($user, $goalData['name'], $goalData['target_amount']);
            $this->consumeFinancialQuota($user, $messageId);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->financeService->renderGoalCreatedCard($goal),
            ]);
        }

        if ($contribData = $this->parser->parseGoalContribution($messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $goal = $this->financeService->findGoalByNameOrId($user, $contribData['goal_name']);
            if (!$goal) {
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => "❌ Target '{$contribData['goal_name']}' tidak ditemukan. Ketik `daftar target` untuk melihat daftar target Anda.",
                ]);
            }

            try {
                $result = $this->financeService->contributeToGoal(
                    $user,
                    $goal,
                    $contribData['amount'],
                    $contribData['wallet_name']
                );
                $this->consumeFinancialQuota($user, $messageId);
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => $this->financeService->renderGoalContributionCard($result),
                ]);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'action'     => 'REPLY_TEXT',
                    'reply_text' => '⚠️ ' . $e->getMessage(),
                ]);
            }
        }

        if (preg_match('/^(?:daftar|list)\s+target$/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->financeService->renderGoalsListCard($user),
            ]);
        }

        // Daily digest preference toggle
        if (preg_match('/^(?:matikan|nonaktifkan)\s+rekap\s+harian$/i', $messageText)) {
            $this->insightsService->setDailyDigestEnabled($user, false);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "🔕 Rekap harian otomatis berhasil dinonaktifkan.\n\nKetik `aktifkan rekap harian` kapan saja untuk menyalakan kembali.",
            ]);
        }

        if (preg_match('/^(?:aktifkan|nyalakan)\s+rekap\s+harian$/i', $messageText)) {
            $this->insightsService->setDailyDigestEnabled($user, true);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "🔔 Rekap harian otomatis berhasil diaktifkan. Anda akan menerima ringkasan setiap pukul 21:00 WIB.",
            ]);
        }

        // Contextual spending insights (US7)
        if (preg_match('/^(?:bulan ini boros|apakah boros|evaluasi pengeluaran)/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->insightsService->renderVarianceAnalysisCard($user),
            ]);
        }

        // Freelancer & Projects summary (US8)
        if (preg_match('/^rekap\s+(?:freelance|bisnis|proyek)/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->financeService->renderFreelanceSummaryCard($user),
            ]);
        }

        // UMKM / Merchant summary (US8)
        if (preg_match('/^(?:omzet|laba)/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->financeService->renderUmkmSummaryCard($user),
            ]);
        }

        // Undo / batal (Typo-tolerant: batalin, undo, cancel)
        if (preg_match('/^(?:batal|hapus transaksi terakhir|undo+|batalin|cancel)$/i', $messageText)) {
            return $this->handleUndo($user);
        }

        // Export Excel (Typo-tolerant: ekspor excel, unduh excel)
        if (preg_match('/^(?:export excel|ekspor excel|laporan excel|download laporan|download excel|unduh excel)$/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleExcelExport($user);
        }

        // Check budgets
        if (preg_match('/^(?:cek|status|list)\s+budg?et$/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->budgetService->renderAllBudgetsCard($user),
            ]);
        }

        // Set budget: "budget <kategori> <nominal>"
        if ($budgetData = $this->parser->parseBudgetSet($messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $cat = $this->financeService->matchCategory($user, $budgetData['category'], 'EXPENSE');
            if (!$cat) {
                $cat = $this->financeService->addCustomCategory($user, $budgetData['category'], 'EXPENSE');
            }
            $budget = $this->budgetService->setBudget($user, $cat->id, $budgetData['amount']);
            $this->consumeFinancialQuota($user, $messageId);
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $this->budgetService->renderSetBudgetCard($budget),
            ]);
        }

        // Category list
        if (preg_match('/^(kategori|daftar kategori|list kategori)$/i', $messageText)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleListCategories($user);
        }

        // Add category: "tambah kategori <name>"
        if (preg_match('/^tambah kategori\s+(.+)$/i', $messageText, $matches)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleAddCategory($user, trim($matches[1]));
        }

        if (preg_match('/^pengeluaran hari ini$/i', $messageText, $matches)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleTransactionList(
                $user,
                'EXPENSE',
                'today'
            );
        }

        if (preg_match('/^pemasukan hari ini$/i', $messageText, $matches)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleTransactionList(
                $user,
                'INCOME',
                'today'
            );
        }

        if (preg_match('/^pengeluaran bulan ini$/i', $messageText, $matches)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleTransactionList(
                $user,
                'EXPENSE',
                'month'
            );
        }

        if (preg_match('/^pemasukan bulan ini$/i', $messageText, $matches)) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleTransactionList(
                $user,
                'INCOME',
                'month'
            );
        }

        // ---------------------------------------------------------------------
        // Quick Correction (Koreksi transaksi terakhir secara instan tanpa ID UUID)
        // Contoh: "koreksi 35rb" atau "ganti nominal 50rb makan siang"
        // ---------------------------------------------------------------------
        // ---------------------------------------------------------------------
        // Koreksi Nominal Transaksi Terakhir (Contoh: "koreksi 30rb", "koreksi 6.850jt", "koreksi 6jt850rb")
        // ---------------------------------------------------------------------
        if (
            preg_match(
                '/^(?:koreksi|ganti nominal|ubah nominal)\s+(.+)$/i',
                $messageText,
                $matches
            )
        ) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $rawInput = trim($matches[1]);
            $amountDetails = $this->parser->extractAmountDetails($rawInput);
            $amount = $amountDetails['amount'] ?? 0;
            $newDesc = null;
            if ($amountDetails) {
                $rem = trim(preg_replace('/' . preg_quote($amountDetails['raw_matched'], '/') . '/i', '', $rawInput, 1));
                $newDesc = !empty($rem) ? $rem : null;
            }

            $lastTx = $user->transactions()->latest('id')->first();

            if (!$lastTx) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ Belum ada transaksi yang dapat dikoreksi.']);
            }

            if ($amount <= 0) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ Nominal koreksi tidak valid. Contoh: `koreksi 35rb`']);
            }

            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleTransactionUpdate(
                $user,
                (string) $lastTx->id,
                $amount,
                $newDesc ?: $lastTx->description
            );
        }

        // ---------------------------------------------------------------------
        // Update Transaction by Number (Contoh: "ubah 2 50rb makan", "ubah no 2 6.850jt")
        // ---------------------------------------------------------------------
        if (
            preg_match(
                '/^(?:ubah|ganti|edit)(?:\s+transaksi)?\s+(?:no\.?|nomor\s+)?(\d+)\s+(.+)$/i',
                $messageText,
                $matches
            )
        ) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $itemNumber = (int) $matches[1];
            $rawInput = trim($matches[2]);
            $amountDetails = $this->parser->extractAmountDetails($rawInput);
            $amount = $amountDetails['amount'] ?? 0;
            $description = null;
            if ($amountDetails) {
                $rem = trim(preg_replace('/' . preg_quote($amountDetails['raw_matched'], '/') . '/i', '', $rawInput, 1));
                $description = !empty($rem) ? $rem : null;
            }

            if ($amount <= 0) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ Nominal tidak valid. Contoh: `ubah 2 50rb makan siang`']);
            }

            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleUpdateByNumber($user, $itemNumber, $amount, $description);
        }

        // ---------------------------------------------------------------------
        // Update Transaction with ID
        //
        // Example:
        // ubah transaksi a82f31c2 6jt850rb makan siang
        // ---------------------------------------------------------------------
        if (
            preg_match(
                '/^(?:ubah|ganti|edit)(?:\s+transaksi)?\s+([a-f0-9-]+)\s+(.+)$/i',
                $messageText,
                $matches
            )
        ) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $transactionId = trim($matches[1]);
            $rawInput = trim($matches[2]);
            $amountDetails = $this->parser->extractAmountDetails($rawInput);
            $amount = $amountDetails['amount'] ?? 0;
            $description = null;
            if ($amountDetails) {
                $rem = trim(preg_replace('/' . preg_quote($amountDetails['raw_matched'], '/') . '/i', '', $rawInput, 1));
                $description = !empty($rem) ? $rem : null;
            }

            if ($amount <= 0) {
                return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => "⚠️ Nominal tidak valid. Contoh: `ubah transaksi {$transactionId} 75000 makan siang`"]);
            }

            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleTransactionUpdate(
                $user,
                $transactionId,
                $amount,
                $description
            );
        }

        // ---------------------------------------------------------------------
        // Delete Transaction by Number (Contoh: "hapus 2", "hapus no 2", "hapus nomor 3", "delete 1")
        // ---------------------------------------------------------------------
        if (
            preg_match(
                '/^(?:hapus|delete|batal)(?:\s+transaksi)?\s+(?:no\.?|nomor\s+)?(\d+)$/i',
                $messageText,
                $matches
            )
        ) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleDeleteByNumber($user, (int) $matches[1]);
        }

        // ---------------------------------------------------------------------
        // Delete Transaction with ID (Contoh: "hapus transaksi a82f31c2", "hapus a82f31c2", "delete a82f31c2")
        // ---------------------------------------------------------------------
        if (
            preg_match(
                '/^(?:hapus|delete)(?:\s+transaksi)?\s+([a-f0-9-]+)$/i',
                $messageText,
                $matches
            )
        ) {
            if ($quotaResp = $this->checkFinancialQuota($user)) return $quotaResp;
            $this->consumeFinancialQuota($user, $messageId);
            return $this->handleTransactionDelete(
                $user,
                trim($matches[1])
            );
        }

        // Privacy Policy / Privasi
        if (preg_match('/^(?:privasi|kebijakan\s+privasi|privacy|privacy\s+policy)$/i', trim($messageText))) {
            $baseUrl = rtrim(config('app.url', url('/')), '/');
            if (request() && request()->getHost()) {
                $req = request();
                $requestHost = $req->header('X-Forwarded-Host') ?: $req->getHost();
                $requestScheme = $req->header('X-Forwarded-Proto') ?: $req->getScheme();
                $requestPort = $req->header('X-Forwarded-Port') ?: $req->getPort();
                $portSuffix = ($requestPort && !in_array((int) $requestPort, [80, 443], true) && !str_contains($requestHost, ':')) ? ":{$requestPort}" : '';
                if ($requestHost && $requestScheme) {
                    $baseUrl = "{$requestScheme}://{$requestHost}{$portSuffix}";
                }
            }
            $privacyUrl = "{$baseUrl}/privasi";

            $text  = "🛡️ *Kebijakan Privasi & Keamanan HematWoy*\n";
            $text .= "───────────────────────\n";
            $text .= "Data finansial Anda dilindungi dengan standar privasi tertinggi:\n\n";
            $text .= "✓ *Enkripsi AES-256-GCM:* Seluruh data nominal tersimpan dalam keadaan terenkripsi.\n";
            $text .= "✓ *Isolasi Ketat:* Database terikat aman dengan nomor ponsel resmi Anda.\n";
            $text .= "✓ *Tanpa Pihak Ketiga:* Kami tidak pernah membagikan atau menjual data transaksi Anda.\n";
            $text .= "✓ *Hak Hapus Penuh:* Anda berhak menghapus seluruh riwayat data Anda kapan saja.\n\n";
            $text .= "📖 *Baca Kebijakan Privasi Lengkap:*\n";
            $text .= "👉 {$privacyUrl}\n\n";
            $text .= "_💡 Butuh menghapus riwayat? Ketik: `hapus data saya`_";

            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $text,
            ]);
        }

        // Data Deletion Request (GDPR / Right to be forgotten)
        if (preg_match('/^(?:hapus\s+data\s+saya|hapus\s+semua\s+data|hapus\s+akun)$/i', trim($messageText))) {
            $isPro = $user->hasActivePremium();
            $expiry = $user->subscription_expires_at ? $user->subscription_expires_at->translatedFormat('d F Y') : null;

            $text  = "⚠️ *Konfirmasi Penghapusan Seluruh Data Finansial*\n";
            $text .= "───────────────────────\n";
            $text .= "Anda akan menghapus *permanen*:\n";
            $text .= "• Seluruh riwayat transaksi (keluar/masuk)\n";
            $text .= "• Seluruh dompet dan saldo kas\n";
            $text .= "• Seluruh plafon anggaran dan target tabungan\n";
            $text .= "• Kredensial brankas dan PIN keamanan\n";

            if ($isPro) {
                $text .= "\n⭐ *PERINGATAN PAKET PRO AKTIF:*\n";
                $text .= "Akun Anda memiliki langganan Pro aktif s/d *{$expiry}*.\n";
                $text .= "Menghapus akun akan *menghanguskan sisa masa aktif langganan Anda tanpa pengembalian dana (non-refundable)*.\n";
            }

            $text .= "\n⚠️ *PENTING:* Tindakan ini bersifat permanen dan *TIDAK DAPAT DIBATALKAN*.\n\n";
            $text .= "Jika Anda benar-benar yakin, ketik persis:\n";
            $text .= "👉 `konfirmasi hapus semua data`";

            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $text,
            ]);
        }

        // Final Confirmation of Data Deletion
        if (preg_match('/^konfirmasi\s+hapus\s+semua\s+data$/i', trim($messageText))) {
            DB::transaction(function () use ($user) {
                // Delete user financial records
                Transaction::where('user_jid', $user->jid)->forceDelete();
                Wallet::where('user_jid', $user->jid)->delete();
                Budget::where('user_jid', $user->jid)->delete();
                FinancialGoal::where('user_jid', $user->jid)->delete();
                RecurringSchedule::where('user_jid', $user->jid)->delete();
                UsageLog::where('user_jid', $user->jid)->delete();

                // Reset user security credentials, subscription, and message count
                $user->forceFill([
                    'pin_hash'                     => null,
                    'recovery_code_hash'           => null,
                    'encryption_salt'              => null,
                    'pin_status'                   => 'UNCONFIGURED',
                    'free_financial_message_count' => 0,
                    'last_pin_verified_at'         => null,
                    'tier'                         => 'FREE',
                    'subscription_expires_at'      => null,
                    'current_balance'              => 0,
                ])->save();

                // Revoke Sanctum web tokens
                $user->tokens()->delete();
            });

            Cache::forget("vault_unlocked:{$user->jid}");

            $text  = "✅ *Seluruh Data Anda Berhasil Dihapus Permanen*\n";
            $text .= "───────────────────────\n";
            $text .= "Seluruh riwayat transaksi, saldo dompet, anggaran, target tabungan, dan sesi login Anda telah tuntas dibersihkan dari server kami.\n\n";
            $text .= "Terima kasih telah mempercayai HematWoy. Jika Anda ingin mulai mencatat kembali dari awal di masa depan, silakan kirim transaksi baru kapan saja. 👋";

            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => $text,
            ]);
        }

        // Help / menu (Typo-tolerant & Natural phrasing)
        if (preg_match('/^(?:bantuan+|help|menu|\?|panduan|tolong|cara pakai|cara guna(?:kan)?|gimana cara(?:nya)?|bisa apa(?: aja)?|fitur(?: apa aja)?|kamu bot apa|daftar perintah|perintah(?: apa aja)?)$/i', trim($messageText))) {
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

        if ($user->pin_status !== 'ACTIVE') {
            return response()->json([
                'action'     => 'REPLY_TEXT',
                'reply_text' => "👋 Halo! Demi menjaga kerahasiaan keuanganmu, buat PIN atau Kata Sandi akunmu dulu yuk 🔐\n\n"
                    . "🔒 *Keamanan Data Pribadi (Zero-Knowledge Enkripsi)*\n"
                    . "Data nominalmu dienkripsi rapat sehingga hanya kamu yang dapat melihatnya.\n\n"
                    . "👉 Cukup ketik: `set pin <pin_pilihanmu>`\n"
                    . "• Contoh angka: `set pin 123456`\n"
                    . "• Contoh kata sandi: `set pin rahasia123`\n"
                    . "_(Minimal 6 karakter, bebas kombinasi angka atau huruf)_\n\n"
                    . "Setelah PIN dibuat, transaksi pertamamu bisa langsung dicatat! 🚀",
            ]);
        }
        if ($quotaResp = $this->checkFinancialQuota($user)) {
            return $quotaResp;
        }
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

            // Mengambil 8 karakter terakhir UUID v7 agar unik (menghindari timestamp kembar di depan)
            $shortId = substr(
                (string) $transaction->id,
                -8
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

        // Simpan referensi ID transaksi terakhir yang dilihat pengguna untuk kemudahan perintah "hapus 2"
        Cache::put("user_recent_tx:{$user->jid}", $transactions->pluck('id')->values()->all(), now()->addHours(2));

        $text .= "━━━━━━━━━━━━━━━━━\n";
        $text .= "💰 Saldo: "
            . $this->formatRupiah(
                $this->financeService->getBalance($user)
            )
            . "\n\n";

        $text .= "_Pilihan Cepat:_\n";
        $text .= "• `hapus <nomor>` (contoh: *hapus 2*)\n";
        $text .= "• `ubah <nomor> <nominal> <ket>` (contoh: *ubah 2 50rb bensin*)\n";
        $text .= "• Atau via ID: `hapus {$shortId}`";

        return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => $text]);
    }


    // =========================================================================
    // Number-Based Handlers (UX Ramah Pengguna)
    // =========================================================================

    private function handleDeleteByNumber(User $user, int $itemNumber): JsonResponse
    {
        if ($itemNumber <= 0) {
            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ Nomor transaksi harus minimal 1. Contoh: `hapus 1`']);
        }

        $txIds = Cache::get("user_recent_tx:{$user->jid}");
        $targetTxId = null;

        if (is_array($txIds) && isset($txIds[$itemNumber - 1])) {
            $targetTxId = $txIds[$itemNumber - 1];
        } else {
            // Fallback: cari dari transaksi aktif terbaru pengguna
            $recent = $user->transactions()
                ->where('status', 'ACTIVE')
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->skip($itemNumber - 1)
                ->first();

            if ($recent) {
                $targetTxId = $recent->id;
            }
        }

        if (!$targetTxId) {
            return response()->json([
                'action' => 'REPLY_TEXT',
                'reply_text' => "⚠️ Transaksi nomor {$itemNumber} tidak ditemukan. Ketik `pemasukan hari ini` atau `pengeluaran hari ini` untuk melihat daftar transaksi aktif.",
            ]);
        }

        return $this->handleTransactionDelete($user, $targetTxId, $itemNumber);
    }

    private function handleUpdateByNumber(User $user, int $itemNumber, int $amount, ?string $description): JsonResponse
    {
        if ($itemNumber <= 0) {
            return response()->json(['action' => 'REPLY_TEXT', 'reply_text' => '⚠️ Nomor transaksi harus minimal 1. Contoh: `ubah 1 50000 makan`']);
        }

        $txIds = Cache::get("user_recent_tx:{$user->jid}");
        $targetTxId = null;

        if (is_array($txIds) && isset($txIds[$itemNumber - 1])) {
            $targetTxId = $txIds[$itemNumber - 1];
        } else {
            $recent = $user->transactions()
                ->where('status', 'ACTIVE')
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->skip($itemNumber - 1)
                ->first();

            if ($recent) {
                $targetTxId = $recent->id;
            }
        }

        if (!$targetTxId) {
            return response()->json([
                'action' => 'REPLY_TEXT',
                'reply_text' => "⚠️ Transaksi nomor {$itemNumber} tidak ditemukan. Ketik `pemasukan hari ini` atau `pengeluaran hari ini` untuk melihat daftar transaksi aktif.",
            ]);
        }

        return $this->handleTransactionUpdate($user, $targetTxId, $amount, $description ?: '', $itemNumber);
    }

    // =========================================================================
    // Update
    // =========================================================================

    private function handleTransactionUpdate(
        User $user,
        string $transactionId,
        int $amount,
        string $description,
        ?int $itemNumber = null
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
                $description ?: $transaction->description
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

            $header = $itemNumber
                ? "✅ *Transaksi #{$itemNumber} Berhasil Diubah*\n"
                : "✅ *Transaksi Berhasil Diubah*\n";

            $text = $header;
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
        string $transactionId,
        ?int $itemNumber = null
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

            // Hapus dari cache recent jika ada
            $txIds = Cache::get("user_recent_tx:{$user->jid}");
            if (is_array($txIds)) {
                $filtered = array_values(array_filter($txIds, fn ($id) => $id !== $transaction->id));
                Cache::put("user_recent_tx:{$user->jid}", $filtered, now()->addHours(2));
            }

            $voided = $result['voided'];

            $typeLabel = $voided->type === 'EXPENSE'
                ? 'Pengeluaran'
                : 'Pemasukan';

            $header = $itemNumber
                ? "🗑️ *Transaksi #{$itemNumber} Berhasil Dihapus*\n"
                : "🗑️ *Transaksi Berhasil Dihapus*\n";

            $text = $header;
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
        $walletsCard = $this->financeService->renderWalletsCard($user);
        $daily       = $this->financeService->getDailySummary($user, now()->toDateString());

        $text = "💰 *Informasi Saldo*\n" . $walletsCard . "\n\n";
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
        $text   = "↩️ *Transaksi Dibatalkan* (Berhasil Dibatalkan)\n";
        $text  .= "━━━━━━━━━━━━━━━━━\n";
        $text  .= "📝 {$voided->description}\n";
        if ($voided->type === 'TRANSFER') {
            $text .= "🔄 Transfer: " . $this->formatRupiah($voided->amount) . "\n";
        } else {
            $typeLabel = $voided->type === 'EXPENSE' ? 'Pengeluaran' : 'Pemasukan';
            $text .= "💸 {$typeLabel}: " . $this->formatRupiah($voided->amount) . "\n";
        }
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

        $exportId = 'exp_' . (string) Str::ulid();
        Cache::put("export:{$exportId}", [
            'user_jid'   => $user->jid,
            'purpose'    => 'whatsapp_excel',
            'created_at' => now()->timestamp,
        ], 90);

        Log::info('Export requested', [
            'export_id' => $exportId,
            'type'      => 'excel',
        ]);

        $fileUrl = URL::temporarySignedRoute(
            'api.export.excel',
            now()->addSeconds(90),
            ['export_id' => $exportId]
        );

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
                    'type'        => $parsed['type'],
                    'amount'      => $parsed['amount'],
                    'description' => $parsed['description'],
                    'category_id' => $category?->id,
                    'wallet_name' => $parsed['wallet_tag'] ?? null,
                ]);
                return [$transaction, $this->subscriptions->consume($user, $messageId)];
            });

            $user->refresh();
            $typeLabel    = $parsed['type'] === 'EXPENSE' ? '💸 Pengeluaran' : '💰 Pemasukan';
            $typeEmoji    = '✅';
            $typeTitle    = $parsed['type'] === 'EXPENSE' ? 'Catatan Pengeluaran Tersimpan' : 'Catatan Pemasukan Tersimpan';
            $categoryName = $category ? "{$category->icon} {$category->name}" : '📦 Lain-lain';
            $walletObj    = $transaction->wallet;
            $walletName   = $walletObj ? $walletObj->name : 'Cash';
            $walletBal    = $walletObj ? $this->formatRupiah($walletObj->balance) : $this->formatRupiah($user->current_balance);

            $text  = "{$typeEmoji} *{$typeTitle}*\n";
            $text .= "━━━━━━━━━━━━━━━━━\n";

            if (!empty($parsed['description'])) {
                $text .= "📝 *Keterangan*: {$parsed['description']}\n";
            }

            $text .= "🏷️ *Kategori*: {$categoryName}\n";
            $text .= "👛 *Dompet*: {$walletName} (Sisa: {$walletBal})\n";
            $text .= "{$typeLabel}: " . $this->formatRupiah($parsed['amount']) . "\n";
            $text .= "━━━━━━━━━━━━━━━━━\n";

            if ($parsed['type'] === 'EXPENSE' && $category) {
                $budgetSnippet = $this->budgetService->renderExpenseBudgetSnippet($user, $category->id);
                if ($budgetSnippet) {
                    $text .= "\n" . $budgetSnippet . "\n━━━━━━━━━━━━━━━━━\n";
                }
            }

            $text .= "💰 *Total Saldo*: " . $this->formatRupiah($user->current_balance) . "\n\n";
            $text .= "💡 *Aksi Cepat:*\n";
            $text .= "• Koreksi nominal: `koreksi 30rb`\n";
            $text .= "• Batalkan: _Ketik \"batal\" jika ingin membatalkan transaksi ini._";
            if ($user->tier === 'FREE' && $remaining <= 10) {
                $text .= "\n\n💡 *Info Kuota:* Sisa kuota gratis kamu: *{$remaining} transaksi*. Ketik `langganan` untuk upgrade akun Pro tanpa batas! ⭐";
            }

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
        $name = $user->display_name ?: 'Kak';

        return "👋 Halo *{$name}*! Selamat datang di *HematWoy* 💰\n\n"
            . "Asisten finansial pintar harianmu langsung dari WhatsApp!\n\n"
            . "🔒 *Langkah Awal (Keamanan Data):*\n"
            . "Sebelum transaksi pertama, buat Master PIN atau kata sandi pengaman (min. 6 karakter, bebas angka atau huruf):\n"
            . "👉 `set pin 123456` atau `set pin rahasia123`\n\n"
            . "⚡ *Contoh Perintah Cepat:*\n"
            . "• *Catat*: `keluar 25rb makan` atau `+1.5jt gaji`\n"
            . "• *Dompet*: `saldo` atau `transfer 50rb dari Cash ke BCA`\n"
            . "• *Budget*: `budget makan 1jt`\n"
            . "• *Dashboard*: `web` (buka analitik & grafik visual)\n"
            . "• *Panduan*: `menu`\n\n"
            . "_Yuk mulai rapikan keuanganmu hari ini! 🚀_";
    }

    private function buildHelpMessage(): string
    {
        $groupedMenus = BotMenu::active()->get()->groupBy('category');

        if ($groupedMenus->isNotEmpty()) {
            $text = "📚 *Panduan WA Finance Bot (HematWoy)*\n\n";

            foreach ($groupedMenus as $category => $items) {
                $icon = $items->first()->category_icon ? $items->first()->category_icon . ' ' : '';
                $text .= "{$icon}*{$category}:*\n";
                foreach ($items as $item) {
                    $desc = $item->description ? " ({$item->description})" : '';
                    $text .= "  • `{$item->command}`{$desc}\n";
                }
                $text .= "\n";
            }

            $text .= "_💡 Format nominal fleksibel: 25000, 25.000, 25k, 25rb, 1.5jt_";

            return trim($text);
        }

        return "📚 *Panduan WA Finance Bot (HematWoy)*\n\n"
            . "💸 *Catat Pengeluaran:*\n"
            . "  • `keluar 25000 makan siang`\n"
            . "  • `beli bensin 50rb via bca`\n"
            . "  • `bayar listrik 150.000`\n"
            . "  • `-35k parkir`\n\n"
            . "💰 *Catat Pemasukan:*\n"
            . "  • `masuk 500000 gaji`\n"
            . "  • `terima 250k bonus ke bca`\n"
            . "  • `+2jt freelance website client A`\n"
            . "  • `jual paket nasi 750rb` (omzet UMKM)\n\n"
            . "👛 *Dompet & Saldo:*\n"
            . "  • `saldo` (cek rincian saldo per dompet)\n"
            . "  • `tambah dompet BCA saldo 1jt`\n"
            . "  • `transfer 200rb dari Cash ke BCA`\n\n"
            . "🎯 *Plafon Anggaran (Budget):*\n"
            . "  • `budget makan 1jt`\n"
            . "  • `cek budget` (status plafon & bar terpakai)\n\n"
            . "⏰ *Langganan & Transaksi Rutin:*\n"
            . "  • `langganan Netflix 186rb setiap tanggal 15 via dana`\n"
            . "  • `daftar langganan`\n"
            . "  • `hapus langganan Netflix`\n\n"
            . "🎯 *Target Tabungan (Goals):*\n"
            . "  • `buat target Mobil 100jt`\n"
            . "  • `tabung 1jt untuk Mobil via bca`\n"
            . "  • `daftar target`\n\n"
            . "📊 *Laporan & Analisis:*\n"
            . "  • `rekap hari ini` / `rekap bulan ini`\n"
            . "  • `pengeluaran hari ini` / `pemasukan hari ini`\n"
            . "  • `bulan ini boros gak?` (analisis MoM)\n"
            . "  • `omzet hari ini` / `laba hari ini` (laba UMKM)\n"
            . "  • `rekap freelance` (total pendapatan proyek)\n"
            . "  • `export excel` (unduh sheet laporan)\n\n"
            . "🌐 *Web Dashboard & Pengaturan:*\n"
            . "  • `web` (link dashboard grafik & analitik)\n"
            . "  • `koreksi 35rb` (ubah nominal transaksi terakhir)\n"
            . "  • `batal` (batalkan transaksi terakhir)\n"
            . "  • `set pin 123456`\n"
            . "  • `kategori` / `tambah kategori <nama>`\n"
            . "  • `hapus data saya` (hapus permanen seluruh data)\n\n"
            . "_💡 Format nominal fleksibel: 25000, 25.000, 25k, 25rb, 1.5jt_";
    }

    private function buildUnrecognizedMessage(
        string $message
    ): string {
        $quickItems = BotMenu::where('is_quick_command', true)->orderBy('sort_order')->take(5)->get();

        $text = "🤔 Maaf, saya belum mengenali format pesan:\n"
            . "\"_{$message}_\"\n\n"
            . "💡 *Pilihan Bantuan Cepat:*\n";

        if ($quickItems->isNotEmpty()) {
            $emojis = ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣'];
            $i = 0;
            foreach ($quickItems as $item) {
                $emoji = $emojis[$i] ?? "•";
                $text .= "{$emoji} *{$item->category}* 👉 `{$item->command}`\n";
                $i++;
            }
        } else {
            $text .= "1️⃣ *Catat Pengeluaran* 👉 `keluar 25000 makan siang`\n"
                . "2️⃣ *Catat Pemasukan*   👉 `masuk 500000 gaji`\n"
                . "3️⃣ *Cek Saldo*          👉 `saldo`\n"
                . "4️⃣ *Buka Dashboard*     👉 `web`\n"
                . "5️⃣ *Panduan Lengkap*    👉 `menu`\n";
        }

        $text .= "\n_Ketik salah satu perintah di atas ya! 😊_";

        return $text;
    }

    // ---------------------------------------------------------------------------
    // Helpers & Quota Enforcement
    // ---------------------------------------------------------------------------

    /**
     * Check if user is allowed to perform a financial action or inquiry.
     * If free quota is exhausted and user is not premium, returns the subscription upgrade response.
     */
    private function checkFinancialQuota(User $user): ?JsonResponse
    {
        if ($this->subscriptions->canRecord($user)) {
            return null; // Quota OK
        }

        return $this->buildQuotaExhaustedResponse($user);
    }

    /**
     * Deduct one unit of financial quota and record usage.
     */
    private function consumeFinancialQuota(User $user, string $messageId, int $transactionsRecorded = 0): void
    {
        try {
            $this->subscriptions->consume($user, $messageId, $transactionsRecorded);
        } catch (\Throwable $e) {
            Log::warning("Quota consumption error for {$user->jid}: " . $e->getMessage());
        }
    }

    /**
     * Build interactive quota exhausted response without hardcoded limits.
     */
    private function buildQuotaExhaustedResponse(User $user): JsonResponse
    {
        $status = $this->subscriptions->status($user);
        $limit = $status['free_limit'];
        $userName = $user->display_name ?: 'Kak';

        $plans = SubscriptionPlan::active()->get();
        $monthlyPlan = $plans->firstWhere('code', 'monthly') ?? $plans->first();
        $priceDisplay = $monthlyPlan ? $monthlyPlan->formatted_price : 'Rp25.000';

        // Dynamic base URL for payment link
        $req = request();
        $requestHost = $req ? ($req->header('X-Forwarded-Host') ?: $req->getHost()) : null;
        $requestScheme = $req ? ($req->header('X-Forwarded-Proto') ?: $req->getScheme()) : null;
        $requestPort = $req ? ($req->header('X-Forwarded-Port') ?: $req->getPort()) : null;
        $portSuffix = '';
        if ($requestPort && !in_array((int) $requestPort, [80, 443], true) && $requestHost && !str_contains($requestHost, ':')) {
            $portSuffix = ":{$requestPort}";
        }
        $baseUrl = ($requestHost && $requestScheme) ? "{$requestScheme}://{$requestHost}{$portSuffix}" : url('/');

        $order = null;
        try {
            $amount = $monthlyPlan ? (int) $monthlyPlan->price : MidtransService::PRICE;
            $order = $this->midtrans->createOrder($user, $amount, 'HematWoy Pro 30 Hari', $baseUrl);
        } catch (\Throwable $e) {
            Log::warning("Could not auto-generate order for quota exhausted: " . $e->getMessage());
        }

        $payUrl = $order ? ($order->payment_url ?: "{$baseUrl}/pay/{$order->order_id}") : "{$baseUrl}/pricing";

        $reply  = "⭐ *Batas Kuota Finansial Gratis Tercapai* ⭐\n";
        $reply .= "───────────────────────\n";
        $reply .= "Halo *{$userName}*! Kamu telah menggunakan seluruh kuota (*{$limit} pesan & pertanyaan finansial gratis*) di *HematWoy*. 🎉\n\n";
        $reply .= "Untuk melanjutkan pencatatan, tanya saldo, rekap laporan, dan menikmati fitur tanpa batas:\n\n";
        $reply .= "✨ *Keunggulan HematWoy Pro:*\n";
        $reply .= "• Chat & Catat Keuangan Unlimited (Tanpa Batas)\n";
        $reply .= "• Multi-Dompet (BCA, Mandiri, Cash, GoPay, OVO)\n";
        $reply .= "• Download Laporan Excel (.xlsx) Kapan Saja\n";
        $reply .= "• Analisis & Evaluasi Keuangan Pintar\n\n";
        $reply .= "👉 *Upgrade sekarang (mulai {$priceDisplay}/bln):*\n";
        $reply .= "Ketik: `langganan` untuk melihat semua pilihan paket\n\n";
        $reply .= "💳 *Link Pembayaran Cepat:*\n";
        $reply .= "{$payUrl}\n\n";
        $reply .= "_Akun Pro akan langsung aktif otomatis seketika setelah pembayaran berhasil diproses! 🚀_";

        $buttons = [];
        if ($plans->isNotEmpty()) {
            foreach ($plans->take(2) as $p) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => "langganan {$p->code}",
                        'title' => "{$p->name} ({$p->short_price})",
                    ],
                ];
            }
        } else {
            $buttons = [
                ['type' => 'reply', 'reply' => ['id' => 'langganan bulanan', 'title' => 'Pro Bulanan (25rb)']],
                ['type' => 'reply', 'reply' => ['id' => 'langganan tahunan', 'title' => 'Pro 1 Tahun (199rb)']],
            ];
        }

        return response()->json([
            'action'      => 'REPLY_TEXT',
            'reply_text'  => $reply,
            'interactive' => [
                'type' => 'button',
                'header' => ['type' => 'text', 'text' => '⭐ Batas Kuota Gratis Tercapai'],
                'body' => ['text' => $reply],
                'footer' => ['text' => 'Pilih paket di bawah untuk lanjut berlangganan'],
                'action' => ['buttons' => $buttons],
            ],
        ]);
    }

    private function parseAmount(string $value): int
    {
        return $this->parser->parseAmount($value) ?? 0;
    }

    /**
     * Format integer Rupiah as human-readable currency string.
     */
    private function formatRupiah(int $amount): string
    {
        return 'Rp' . number_format($amount, 0, ',', '.');
    }
}
