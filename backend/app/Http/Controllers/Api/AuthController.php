<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\FinanceService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * AuthController
 *
 * Handles WhatsApp OTP-based authentication for Vue 3 Web and React Native Expo clients.
 * Flow: User sends phone → bot sends 6-digit OTP via WhatsApp → client submits OTP → gets Sanctum token
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly WhatsAppNotificationService $whatsappService,
    ) {}

    /**
     * POST /api/v1/auth/request-otp
     * Generates and stores a 6-digit OTP, then queues it for WhatsApp delivery.
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string|min:10|max:15']);

        $phone = preg_replace('/\D/', '', $request->phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        $jid   = $phone . '@s.whatsapp.net';

        $existingUser = User::where('jid', $jid)->first();
        if ($existingUser && ! $existingUser->is_active) {
            $reason = $existingUser->blocked_reason ? " Alasan: {$existingUser->blocked_reason}" : '';
            return response()->json([
                'success' => false,
                'message' => "Akun WhatsApp Anda telah dinonaktifkan.{$reason}",
                'error'   => ['code' => 'USER_BLOCKED', 'message' => "Akun WhatsApp Anda telah dinonaktifkan.{$reason}"],
            ], 403);
        }

        // --- Subscription Enforcement: Web Dashboard is exclusive to HematWoy Pro subscribers ---
        if (! $existingUser || ! $existingUser->hasActivePremium()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses Web Dashboard eksklusif untuk pelanggan aktif HematWoy Pro. Silakan berlangganan terlebih dahulu melalui bot WhatsApp atau halaman paket.',
                'error'   => [
                    'code'    => 'SUBSCRIPTION_REQUIRED',
                    'message' => 'Akses Web Dashboard eksklusif untuk pelanggan aktif HematWoy Pro.',
                ],
            ], 403);
        }

        // --- Rate Limiting: 60-second cooldown per phone / JID ---
        $cooldownKey = "otp_cooldown:{$jid}";
        if (Cache::has($cooldownKey)) {
            $remaining = Cache::get($cooldownKey) - now()->timestamp;
            $seconds = max(1, $remaining);
            return response()->json([
                'success' => false,
                'message' => "Mohon tunggu {$seconds} detik sebelum meminta kode OTP baru.",
                'error'   => ['code' => 'RATE_LIMITED', 'retry_after' => $seconds],
            ], 429);
        }

        // --- Rate Limiting: max 5 requests per minute per IP ---
        $ipKey = "otp_ip_count:" . $request->ip();
        $ipCount = Cache::increment($ipKey);
        if ($ipCount === 1) {
            Cache::put($ipKey, 1, now()->addMinute());
        }
        if ($ipCount > 5) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak permintaan OTP dari perangkat Anda. Silakan coba lagi sebentar lagi.',
                'error'   => ['code' => 'TOO_MANY_REQUESTS', 'retry_after' => 60],
            ], 429);
        }

        // Set cooldown 60 seconds
        Cache::put($cooldownKey, now()->addSeconds(60)->timestamp, now()->addSeconds(60));

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache for 5 minutes and append to history buffer
        Cache::put("otp:{$jid}", $otp, now()->addMinutes(5));
        $history = Cache::get("otp_history:{$jid}", []);
        $history[] = $otp;
        $history = array_slice($history, -3); // keep last 3 valid OTPs
        Cache::put("otp_history:{$jid}", $history, now()->addMinutes(5));

        // Send OTP via WhatsApp (Meta WhatsApp Cloud API or Baileys Gateway)
        $delivered = $this->whatsappService->sendOtp($phone, $otp);

        \Illuminate\Support\Facades\Log::info("OTP dispatch for {$jid} (" . ($delivered ? 'delivered' : 'pending') . "): {$otp}");

        \Illuminate\Support\Facades\Log::info("OTP for {$jid}: {$otp}");

        return response()->json([
            'success'   => true,
            'message'   => 'Kode OTP telah dikirimkan ke WhatsApp Anda.',
            'debug_otp' => app()->isLocal() ? $otp : null,
        ]);
    }

    /**
     * POST /api/v1/auth/verify-otp
     * Verifies the OTP and returns a Sanctum personal access token.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'otp'   => 'required|string',
        ]);

        $phone = preg_replace('/\D/', '', $request->phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        $jid = $phone . '@s.whatsapp.net';
        $otp = trim($request->otp);

        // --- Anti-Brute Force Lockout Check ---
        $lockKey = "otp_lockout:{$jid}";
        if (Cache::has($lockKey)) {
            $remaining = Cache::get($lockKey) - now()->timestamp;
            $seconds = max(1, $remaining);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan OTP gagal. Verifikasi dibekukan sementara selama {$seconds} detik demi keamanan.",
                'error'   => ['code' => 'LOCKED_OUT', 'retry_after' => $seconds],
            ], 429);
        }

        $storedOtp = Cache::get("otp:{$jid}");
        $history   = Cache::get("otp_history:{$jid}", []);

        \Illuminate\Support\Facades\Log::info("Verify OTP attempt for {$jid}: provided={$otp}, stored={$storedOtp}, history=" . json_encode($history));

        $isValid = ($storedOtp && $storedOtp === $otp) 
            || in_array($otp, $history, true)
            || (app()->isLocal() && $otp === '123456');

        if (!$isValid) {
            // Track failed attempts
            $failKey = "otp_fail_count:{$jid}";
            $fails = Cache::increment($failKey);
            if ($fails === 1) {
                Cache::put($failKey, 1, now()->addMinutes(10));
            }

            if ($fails >= 5) {
                // Invalidate active OTPs & lock for 5 minutes
                Cache::forget("otp:{$jid}");
                Cache::forget("otp_history:{$jid}");
                Cache::forget($failKey);
                Cache::put($lockKey, now()->addMinutes(5)->timestamp, now()->addMinutes(5));

                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan OTP salah (5x). Kode OTP dibatalkan dan verifikasi dibekukan selama 5 menit.',
                    'error'   => ['code' => 'LOCKED_OUT', 'retry_after' => 300],
                ], 429);
            }

            $remainingAttempts = 5 - $fails;
            return response()->json([
                'success' => false,
                'message' => "Kode OTP tidak valid atau sudah kadaluarsa. Sisa kesempatan: {$remainingAttempts} kali.",
                'error'   => ['code' => 'INVALID_OTP', 'remaining_attempts' => $remainingAttempts],
            ], 422);
        }

        // Clear OTP and brute-force tracking
        Cache::forget("otp:{$jid}");
        Cache::forget("otp_history:{$jid}");
        Cache::forget("otp_fail_count:{$jid}");
        Cache::forget($lockKey);
        $resolvedLid = Cache::get("otp_lid:{$jid}");

        // Find or create user, linking phone and resolved LID seamlessly
        $user  = $this->financeService->findOrCreateUser($jid, '', $phone, $resolvedLid);

        if (! $user->is_active) {
            $reason = $user->blocked_reason ? " Alasan: {$user->blocked_reason}" : '';
            return response()->json([
                'success' => false,
                'message' => "Akun WhatsApp Anda telah dinonaktifkan.{$reason}",
                'error'   => ['code' => 'USER_BLOCKED', 'message' => "Akun WhatsApp Anda telah dinonaktifkan.{$reason}"],
            ], 403);
        }

        // --- Subscription Enforcement: Web Dashboard is exclusive to HematWoy Pro subscribers ---
        if (! $user->hasActivePremium()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses Web Dashboard eksklusif untuk pelanggan aktif HematWoy Pro. Silakan berlangganan terlebih dahulu melalui bot WhatsApp atau halaman paket.',
                'error'   => [
                    'code'    => 'SUBSCRIPTION_REQUIRED',
                    'message' => 'Akses Web Dashboard eksklusif untuk pelanggan aktif HematWoy Pro.',
                ],
            ], 403);
        }

        $token = $user->createToken('wa-finance-bot')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil!',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'jid'                     => $user->jid,
                    'phone'                   => $phone,
                    'display_name'            => $user->display_name ?: $phone,
                    'name'                    => $user->display_name ?: $phone,
                    'current_balance'         => $user->current_balance,
                    'tier'                    => $user->tier,
                    'is_pro'                  => $user->hasActivePremium(),
                    'subscription_expires_at' => $user->subscription_expires_at?->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Revokes the user's current Sanctum access token on the server.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout. Sesi telah dihentikan.',
        ]);
    }

    /**
     * GET /api/v1/profile
     * Returns the authenticated user's profile and current balance.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'jid'                     => $user->jid,
                'phone'                   => $user->phone_number ?: str_replace('@s.whatsapp.net', '', $user->jid),
                'display_name'            => $user->display_name,
                'current_balance'         => $user->current_balance,
                'tier'                    => $user->tier,
                'is_pro'                  => $user->hasActivePremium(),
                'subscription_expires_at' => $user->subscription_expires_at?->toISOString(),
                'created_at'              => $user->created_at?->toISOString(),
            ],
        ]);
    }
}
