<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\MidtransService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * SubscriptionController
 *
 * Handles subscription status inquiry and payment initiation.
 */
class SubscriptionController extends Controller
{
    /**
     * GET /api/subscription/status
     */
    public function status(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $jid = $request->user_jid ?? $request->user()?->jid;
        $user = User::findOrFail($jid);

        return response()->json($subscriptions->status($user));
    }

    /**
     * POST /api/subscription/purchase
     */
    public function purchase(Request $request, MidtransService $midtrans): JsonResponse
    {
        $data = $request->validate([
            'user_jid' => 'required|string',
        ]);

        $user = User::findOrFail($data['user_jid']);

        return response()->json([
            'order' => $midtrans->createOrder($user),
        ], 201);
    }
}
