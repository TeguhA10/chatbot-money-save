<?php

namespace App\Http\Controllers\Api;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * SubscriptionController
 *
 * Handles subscription status inquiry, Web App checkout initiation,
 * and gateway purchase calls.
 */
class SubscriptionController extends Controller
{
    /**
     * GET /api/v1/subscription/status or /api/subscription/status
     */
    public function status(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $user = $request->user();
        if (!$user && $request->has('user_jid')) {
            $user = User::find($request->user_jid);
        }

        if (!$user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $subscriptions->status($user),
        ]);
    }

    /**
     * POST /api/v1/subscription/create-order
     * Web App in-browser upgrade initiation (Sanctum authenticated).
     */
    public function createOrder(Request $request, MidtransService $midtrans): JsonResponse
    {
        $data = $request->validate([
            'plan' => 'nullable|string',
        ]);

        /** @var User $user */
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $planCode = $data['plan'] ?? 'monthly';
        $planModel = SubscriptionPlan::where('code', $planCode)->where('is_active', true)->first();

        $isAnnual = $planCode === 'annual';
        $amount = $planModel ? (int) $planModel->price : ($isAnnual ? MidtransService::ANNUAL_PRICE : MidtransService::PRICE);
        $planName = $planModel ? "HematWoy {$planModel->name}" : ($isAnnual ? 'HematWoy Pro 1 Tahun' : 'HematWoy Pro 30 Hari');

        // Resolve dynamic public base URL
        $requestHost = $request->header('X-Forwarded-Host') ?: $request->getHost();
        $requestScheme = $request->header('X-Forwarded-Proto') ?: $request->getScheme();
        $requestPort = $request->header('X-Forwarded-Port') ?: $request->getPort();

        $portSuffix = '';
        if ($requestPort && !in_array((int) $requestPort, [80, 443], true) && $requestHost && !str_contains($requestHost, ':')) {
            $portSuffix = ":{$requestPort}";
        }
        $baseUrl = ($requestHost && $requestScheme) ? "{$requestScheme}://{$requestHost}{$portSuffix}" : url('/');

        $order = $midtrans->createOrder($user, $amount, $planName, $baseUrl);

        return response()->json([
            'success'       => true,
            'order_id'      => $order->order_id,
            'snap_token'    => $order->snap_token,
            'payment_url'   => $order->payment_url ?: "{$baseUrl}/pay/{$order->order_id}",
            'gross_amount'  => $order->gross_amount,
            'plan'          => $planCode,
            'plan_name'     => $planName,
            'client_key'    => config('services.midtrans.client_key'),
            'is_production' => (bool) config('services.midtrans.is_production', false),
        ], 201);
    }

    /**
     * POST /api/subscription/purchase (Legacy / Gateway compatibility)
     */
    public function purchase(Request $request, MidtransService $midtrans): JsonResponse
    {
        $data = $request->validate([
            'user_jid' => 'required|string',
            'plan'     => 'nullable|string',
        ]);

        $user = User::findOrFail($data['user_jid']);
        $planCode = $data['plan'] ?? 'monthly';
        $planModel = SubscriptionPlan::where('code', $planCode)->where('is_active', true)->first();

        $isAnnual = $planCode === 'annual';
        $amount = $planModel ? (int) $planModel->price : ($isAnnual ? MidtransService::ANNUAL_PRICE : MidtransService::PRICE);
        $planName = $planModel ? "HematWoy {$planModel->name}" : ($isAnnual ? 'HematWoy Pro 1 Tahun' : 'HematWoy Pro 30 Hari');

        $order = $midtrans->createOrder($user, $amount, $planName);

        return response()->json([
            'order' => $order,
        ], 201);
    }
}
