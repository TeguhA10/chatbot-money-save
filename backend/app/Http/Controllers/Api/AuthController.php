<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\FinanceService;
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
    public function __construct(private readonly FinanceService $financeService) {}

    /**
     * POST /api/v1/auth/request-otp
     * Generates and stores a 6-digit OTP, then queues it for WhatsApp delivery.
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string|min:10|max:15']);

        $phone = preg_replace('/\D/', '', $request->phone);
        $jid   = $phone . '@s.whatsapp.net';

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache for 5 minutes
        Cache::put("otp:{$jid}", $otp, now()->addMinutes(5));

        // In production, this would trigger the Baileys gateway to send the OTP via WhatsApp.
        // For local dev/testing, we log it.
        \Illuminate\Support\Facades\Log::info("OTP for {$jid}: {$otp}");

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP telah dikirimkan ke WhatsApp Anda.',
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
            'otp'   => 'required|string|size:6',
        ]);

        $phone = preg_replace('/\D/', '', $request->phone);
        $jid   = $phone . '@s.whatsapp.net';

        $storedOtp = Cache::get("otp:{$jid}");

        if (!$storedOtp || $storedOtp !== $request->otp) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_OTP', 'message' => 'Kode OTP tidak valid atau sudah kadaluarsa.'],
            ], 422);
        }

        Cache::forget("otp:{$jid}");

        // Find or create user, then issue Sanctum token
        $user  = $this->financeService->findOrCreateUser($jid, '');
        $token = $user->createToken('wa-finance-bot')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => [
                    'jid'             => $user->jid,
                    'display_name'    => $user->display_name,
                    'current_balance' => $user->current_balance,
                ],
            ],
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
                'jid'             => $user->jid,
                'display_name'    => $user->display_name,
                'current_balance' => $user->current_balance,
                'created_at'      => $user->created_at?->toISOString(),
            ],
        ]);
    }
}
