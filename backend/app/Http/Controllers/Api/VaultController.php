<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\EncryptionService;
use App\Services\PinLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * VaultController
 *
 * Implements Zero-Knowledge Vault Security for Web & Mobile clients:
 * - Master PIN/Passphrase setup (alphanumeric support)
 * - Vault Unlock with brute-force attack mitigation (rate limiting & lockout)
 * - Vault Lock for instant privacy protection
 * - Emergency Vault Recovery via Recovery Code
 */
class VaultController extends Controller
{
    public function __construct(
        private readonly EncryptionService $encryption,
        private readonly PinLifecycleService $pins,
    ) {}

    /**
     * GET /api/v1/vault/status
     * Returns the user's current vault security status.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $tokenId = $user->currentAccessToken()?->id ?? 'default';
        $unlockedKey = "vault_unlocked:{$user->jid}:{$tokenId}";
        $isUnlocked  = (bool) Cache::get($unlockedKey, false);

        return response()->json([
            'success' => true,
            'data'    => [
                'pin_status'      => $user->pin_status ?? 'PENDING_SETUP',
                'is_unlocked'     => $isUnlocked,
                'encryption_salt' => $user->encryption_salt,
                'key_version'     => $user->key_version ?? 1,
                'has_recovery'    => !empty($user->recovery_code_hash),
            ],
        ]);
    }

    /**
     * POST /api/v1/vault/setup
     * Sets up a new Master PIN / Passphrase (alphanumeric allowed, min 6 chars).
     */
    public function setup(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string|min:6|max:64',
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->pin_status === 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Master PIN / Passphrase sudah aktif. Gunakan menu reset jika lupa.',
            ], 400);
        }

        try {
            $recoveryCode = $this->pins->setup($user, $request->pin);

            // Auto-unlock vault for the current session upon setup
            $tokenId = $user->currentAccessToken()?->id ?? 'default';
            Cache::put("vault_unlocked:{$user->jid}:{$tokenId}", true, now()->addMinutes(30));

            return response()->json([
                'success' => true,
                'message' => 'Master PIN / Passphrase keamanan berhasil diaktifkan.',
                'data'    => [
                    'recovery_code'   => $recoveryCode,
                    'encryption_salt' => $user->encryption_salt,
                    'is_unlocked'     => true,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/vault/unlock
     * Verifies the Master PIN / Passphrase to unlock financial records.
     * Includes brute-force protection against hackers.
     */
    public function unlock(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string|min:6|max:64',
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->pin_status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'PIN belum diatur. Silakan buat PIN terlebih dahulu.',
            ], 400);
        }

        // --- Brute-force Protection ---
        $failKey  = "vault_fail_count:{$user->jid}";
        $lockKey  = "vault_lockout:{$user->jid}";

        if (Cache::has($lockKey)) {
            $remaining = Cache::get($lockKey) - now()->timestamp;
            $seconds = max(1, $remaining);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan gagal. Vault dikunci sementara selama {$seconds} detik demi keamanan data Anda.",
                'error'   => ['code' => 'RATE_LIMITED', 'retry_after' => $seconds],
            ], 429);
        }

        $isValid = $this->encryption->verifyPin($user, $request->pin);

        if (!$isValid) {
            $fails = Cache::increment($failKey);
            Cache::put($failKey, $fails, now()->addMinutes(10));

            if ($fails >= 5) {
                // Lockout user for 5 minutes
                $until = now()->addMinutes(5)->timestamp;
                Cache::put($lockKey, $until, now()->addMinutes(5));
                Cache::forget($failKey);

                Log::warning("Vault brute-force threshold reached for {$user->jid}. Account locked for 5 minutes.");

                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan salah (5x). Vault dibekukan sementara selama 5 menit demi mencegah peretasan.',
                    'error'   => ['code' => 'LOCKED_OUT', 'retry_after' => 300],
                ], 429);
            }

            $remainingAttempts = 5 - $fails;
            return response()->json([
                'success' => false,
                'message' => "PIN / Passphrase tidak sesuai. Sisa kesempatan: {$remainingAttempts} kali sebelum terkunci.",
                'error'   => ['code' => 'INVALID_PIN', 'remaining_attempts' => $remainingAttempts],
            ], 422);
        }

        // Reset failed counter
        Cache::forget($failKey);
        Cache::forget($lockKey);

        // Mark unlocked for current session (30 minutes TTL)
        $tokenId = $user->currentAccessToken()?->id ?? 'default';
        Cache::put("vault_unlocked:{$user->jid}:{$tokenId}", true, now()->addMinutes(30));

        return response()->json([
            'success' => true,
            'message' => 'Vault berhasil dibuka!',
            'data'    => [
                'is_unlocked' => true,
            ],
        ]);
    }

    /**
     * POST /api/v1/vault/lock
     * Instantly locks the user's vault session.
     */
    public function lock(Request $request): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $tokenId = $user->currentAccessToken()?->id ?? 'default';
        Cache::forget("vault_unlocked:{$user->jid}:{$tokenId}");

        return response()->json([
            'success' => true,
            'message' => 'Vault berhasil dikunci. Data finansial terlindungi.',
            'data'    => [
                'is_unlocked' => false,
            ],
        ]);
    }

    /**
     * POST /api/v1/vault/reset
     * Resets the PIN using the emergency 16-character Recovery Code.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'recovery_code' => 'required|string|size:16',
            'new_pin'       => 'required|string|min:6|max:64',
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->pins->reset($user, strtoupper(trim($request->recovery_code)), $request->new_pin);

            // Re-unlock with new PIN
            $tokenId = $user->currentAccessToken()?->id ?? 'default';
            Cache::put("vault_unlocked:{$user->jid}:{$tokenId}", true, now()->addMinutes(30));

            return response()->json([
                'success' => true,
                'message' => 'PIN / Passphrase berhasil direset.',
                'data'    => [
                    'is_unlocked' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
