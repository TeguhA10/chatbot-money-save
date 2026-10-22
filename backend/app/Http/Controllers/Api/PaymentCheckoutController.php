<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentCheckoutController
 *
 * Provides endpoints for in-browser checkout, Midtrans Snap status verification,
 * and sandbox payment simulation.
 */
class PaymentCheckoutController extends Controller
{
    public function __construct(
        protected ?WhatsAppNotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? app(WhatsAppNotificationService::class);
    }

    /**
     * GET /api/v1/payment/order/{orderId}
     * Retrieve order details for checkout page.
     */
    public function show(string $orderId): JsonResponse
    {
        $order = PaymentOrder::where('order_id', $orderId)->first();

        if (!$order) {
            return response()->json([
                'error' => 'Order tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'order_id'      => $order->order_id,
            'user_jid'      => $order->user_jid,
            'gross_amount'  => $order->gross_amount,
            'currency'      => $order->currency ?: 'IDR',
            'status'        => $order->status,
            'snap_token'    => $order->snap_token,
            'payment_url'   => $order->payment_url,
            'paid_at'       => $order->paid_at,
            'client_key'    => config('services.midtrans.client_key'),
            'is_production' => (bool) config('services.midtrans.is_production', false),
            'plan_name'     => (int) $order->gross_amount >= 150000 ? 'HematWoy Pro 1 Tahun' : 'HematWoy Pro 30 Hari',
        ]);
    }

    /**
     * POST /api/v1/payment/charge/{orderId}
     * Charge via Midtrans Core API without popup.
     */
    public function charge(Request $request, string $orderId, MidtransService $midtrans): JsonResponse
    {
        $order = PaymentOrder::where('order_id', $orderId)->first();

        if (!$order) {
            return response()->json(['error' => 'Order tidak ditemukan.'], 404);
        }

        if ($order->status === 'SETTLEMENT') {
            return response()->json([
                'success' => true,
                'status'  => 'SETTLEMENT',
                'message' => 'Pesanan sudah lunas.',
            ]);
        }

        $method = $request->input('payment_method', 'qris');
        $result = $midtrans->charge($order, $method);

        return response()->json($result);
    }

    /**
     * POST /api/v1/payment/simulate/{orderId}
     * Simulate payment settlement in sandbox/demo environment.
     */
    public function simulate(Request $request, string $orderId): JsonResponse
    {
        $order = PaymentOrder::where('order_id', $orderId)->first();

        if (!$order) {
            return response()->json(['error' => 'Order tidak ditemukan.'], 404);
        }

        if ($order->status === 'SETTLEMENT') {
            return response()->json([
                'message' => 'Order sudah dibayar sebelumnya.',
                'order'   => $order,
            ]);
        }

        $user = null;
        DB::transaction(function () use ($order, &$user) {
            /** @var User $user */
            $user = User::whereKey($order->user_jid)->lockForUpdate()->firstOrFail();

            $days = (int) $order->gross_amount >= 150000 ? 365 : 30;
            $start = $user->subscription_expires_at?->isFuture() ? $user->subscription_expires_at : now();
            $end = $start->copy()->addDays($days);

            $order->update([
                'status'       => 'SETTLEMENT',
                'paid_at'      => now(),
                'payment_type' => 'qris_simulated',
            ]);

            $user->update([
                'tier'                    => 'PREMIUM',
                'subscription_expires_at' => $end,
            ]);

            Subscription::create([
                'user_jid'        => $user->jid,
                'starts_at'       => $start,
                'ends_at'         => $end,
                'source_order_id' => $order->order_id,
            ]);
        });

        $order->refresh();
        if ($user) {
            $user->refresh();
            try {
                $this->notificationService?->sendPaymentSuccessNotification($order, $user);
            } catch (\Throwable $e) {
                Log::warning('PaymentCheckoutController: Error sending simulation notification: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Pembayaran berhasil dikonfirmasi! Akun HematWoy Pro aktif.',
            'order'   => $order,
        ]);
    }
}
