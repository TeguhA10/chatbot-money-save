<?php

namespace App\Http\Controllers\Api;

use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * MidtransWebhookController
 *
 * Handles HTTP notification webhooks from Midtrans payment gateway.
 */
class MidtransWebhookController extends Controller
{
    /**
     * POST /api/webhook/midtrans
     */
    public function handle(Request $request, MidtransService $midtrans): JsonResponse
    {
        try {
            $order = $midtrans->processNotification($request->all());

            return response()->json([
                'success'  => true,
                'order_id' => $order->order_id,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
