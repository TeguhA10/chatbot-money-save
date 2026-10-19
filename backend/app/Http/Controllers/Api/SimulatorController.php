<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Wallet;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * SimulatorController
 *
 * Provides real-time WhatsApp bot response simulation for the public landing page.
 * Implements strict attempt limits (3 tries maximum per visitor) across localStorage,
 * visitor tokens, and IP tracking to prevent circumventing by page refresh.
 */
class SimulatorController extends Controller
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly WebhookController $webhookController,
        private readonly FinanceService $financeService,
    ) {}

    /**
     * POST /api/v1/simulator
     */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_text'  => 'required|string|max:500',
            'visitor_token' => 'nullable|string|max:100',
        ]);

        $ip = $request->ip() ?: '127.0.0.1';
        $token = $validated['visitor_token'] ?: 'anon';

        $ipKey    = 'sim_att_ip:' . md5($ip);
        $tokenKey = 'sim_att_tok:' . md5($token);

        $ipAttempts    = (int) Cache::get($ipKey, 0);
        $tokenAttempts = (int) Cache::get($tokenKey, 0);
        $currentAttempts = max($ipAttempts, $tokenAttempts);

        if ($currentAttempts >= self::MAX_ATTEMPTS) {
            return response()->json([
                'status'       => 'LIMIT_REACHED',
                'error'        => 'Kuota coba gratis di web telah habis (3/3 kali). Silakan lanjut di WhatsApp.',
                'attempts'     => self::MAX_ATTEMPTS,
                'max_attempts' => self::MAX_ATTEMPTS,
            ], 429);
        }

        // Increment attempts (persisted for 7 days)
        $newAttempts = $currentAttempts + 1;
        Cache::put($ipKey, $newAttempts, now()->addDays(7));
        if ($validated['visitor_token']) {
            Cache::put($tokenKey, $newAttempts, now()->addDays(7));
        }

        // Demo isolated profile
        $demoJid = 'simulator_visitor@s.whatsapp.net';
        $user = User::firstOrCreate(
            ['jid' => $demoJid],
            [
                'display_name'    => 'Pengunjung Web',
                'current_balance' => 2450000,
                'pin_status'      => 'ACTIVE',
                'tier'            => 'FREE',
            ]
        );

        // Ensure demo wallets exist (Cash & BCA)
        if (!Wallet::where('user_jid', $demoJid)->where('name', 'BCA')->exists()) {
            Wallet::create([
                'user_jid'   => $demoJid,
                'name'       => 'BCA',
                'balance'    => 2100000,
                'is_default' => false,
            ]);
        }

        // Execute live Webhook message processor
        $res = $this->webhookController->processMessage(
            messageId: 'sim-' . Str::uuid(),
            fromJid: $demoJid,
            pushName: 'Pengunjung Web',
            messageText: $validated['message_text']
        );

        $data = $res->getData(true);
        $replyText = $data['reply_text'] ?? 'Format pesan tidak dikenali.';

        return response()->json([
            'status'       => 'OK',
            'reply_text'   => $replyText,
            'attempts'     => $newAttempts,
            'max_attempts' => self::MAX_ATTEMPTS,
        ]);
    }

    /**
     * GET /api/v1/simulator/status
     * Check visitor's current attempt count.
     */
    public function status(Request $request): JsonResponse
    {
        $ip = $request->ip() ?: '127.0.0.1';
        $token = $request->query('visitor_token', 'anon');

        $ipKey    = 'sim_att_ip:' . md5($ip);
        $tokenKey = 'sim_att_tok:' . md5($token);

        $ipAttempts    = (int) Cache::get($ipKey, 0);
        $tokenAttempts = (int) Cache::get($tokenKey, 0);
        $currentAttempts = max($ipAttempts, $tokenAttempts);

        return response()->json([
            'attempts'     => min(self::MAX_ATTEMPTS, $currentAttempts),
            'max_attempts' => self::MAX_ATTEMPTS,
            'is_locked'    => $currentAttempts >= self::MAX_ATTEMPTS,
        ]);
    }
}
