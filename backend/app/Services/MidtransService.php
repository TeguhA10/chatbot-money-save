<?php

namespace App\Services;

use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MidtransService
 *
 * Handles order creation, Midtrans Snap token generation,
 * webhook signature verification, and automated subscription activation.
 */
class MidtransService
{
    public const PRICE = 25000;
    public const ANNUAL_PRICE = 199000;

    /**
     * Get price for given plan code from database, with fallback to constants.
     */
    public static function getPriceForPlan(string $planCode = 'monthly'): int
    {
        $plan = SubscriptionPlan::where('code', $planCode)->where('is_active', true)->first();
        if ($plan) {
            return (int) $plan->price;
        }

        return $planCode === 'annual' ? self::ANNUAL_PRICE : self::PRICE;
    }

    /**
     * Get SubscriptionPlan model for plan code.
     */
    public static function getPlan(string $planCode = 'monthly'): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('code', $planCode)->where('is_active', true)->first();
    }

    public function __construct(
        protected ?WhatsAppNotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? app(WhatsAppNotificationService::class);
    }

    /**
     * Create a new payment order for user subscription with Midtrans Snap integration.
     */
    public function createOrder(User $user, int $amount = self::PRICE, string $planName = 'HematWoy Pro 30 Hari', ?string $baseUrl = null): PaymentOrder
    {
        $orderId = 'SUB-' . Str::upper(Str::random(18));
        $serverKey = config('services.midtrans.server_key');

        $snapToken = null;
        $paymentUrl = $baseUrl ? rtrim($baseUrl, '/') . "/pay/{$orderId}" : url("/pay/{$orderId}");

        if (!empty($serverKey)) {
            try {
                $isProduction = (bool) config('services.midtrans.is_production', false);
                $apiUrl = $isProduction
                    ? 'https://app.midtrans.com/snap/v1/transactions'
                    : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

                $phone = $user->phone_number;
                if (!$phone && str_ends_with($user->jid, '@s.whatsapp.net')) {
                    $phone = explode('@', $user->jid)[0];
                }

                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])->timeout(8)->post($apiUrl, [
                    'transaction_details' => [
                        'order_id'     => $orderId,
                        'gross_amount' => $amount,
                    ],
                    'item_details' => [
                        [
                            'id'       => 'HEMATWOY-PRO',
                            'price'    => $amount,
                            'quantity' => 1,
                            'name'     => $planName,
                        ],
                    ],
                    'customer_details' => [
                        'first_name' => $user->display_name ?: 'Pengguna',
                        'phone'      => $phone ?: '08123456789',
                    ],
                ]);

                if ($response->successful()) {
                    $snapToken = $response->json('token');
                } else {
                    Log::warning('Midtrans Snap API Warning', [
                        'status' => $response->status(),
                        'body'   => $response->json(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Midtrans Snap Exception: ' . $e->getMessage());
            }
        }

        return PaymentOrder::create([
            'order_id'     => $orderId,
            'user_jid'     => $user->jid,
            'gross_amount' => $amount,
            'currency'     => 'IDR',
            'snap_token'   => $snapToken,
            'payment_url'  => $paymentUrl,
            'status'       => 'PENDING',
        ]);
    }

    /**
     * Verify Midtrans notification signature key.
     */
    public function verify(array $payload): bool
    {
        $required = ['order_id', 'status_code', 'gross_amount', 'signature_key'];
        foreach ($required as $key) {
            if (!isset($payload[$key])) {
                return false;
            }
        }

        $serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY', ''));
        $expected = hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $serverKey);

        return hash_equals($expected, (string) $payload['signature_key']);
    }

    /**
     * Process verified notification webhook and extend/activate user subscription.
     */
    public function processNotification(array $payload): PaymentOrder
    {
        if (!$this->verify($payload)) {
            throw new RuntimeException('Invalid Midtrans signature.');
        }

        return DB::transaction(function () use ($payload) {
            /** @var PaymentOrder $order */
            $order = PaymentOrder::whereKey($payload['order_id'])->lockForUpdate()->firstOrFail();

            if ((int) $order->gross_amount !== (int) $payload['gross_amount']) {
                throw new RuntimeException('Payment amount mismatch.');
            }

            $txStatus = strtolower($payload['transaction_status'] ?? '');
            $order->update([
                'status'               => strtoupper($payload['transaction_status'] ?? 'PENDING'),
                'payment_type'         => $payload['payment_type'] ?? null,
                'last_webhook_payload' => $payload,
            ]);

            if (!in_array($txStatus, ['settlement', 'capture'], true) || $order->paid_at) {
                return $order;
            }

            /** @var User $user */
            $user = User::whereKey($order->user_jid)->lockForUpdate()->firstOrFail();

            // Duration: 365 days if annual, otherwise 30 days
            $daysToAdd = (int) $order->gross_amount >= 150000 ? 365 : 30;

            $start = $user->subscription_expires_at?->isFuture() ? $user->subscription_expires_at : now();
            $end = $start->copy()->addDays($daysToAdd);

            $order->update([
                'paid_at' => now(),
                'status'  => 'SETTLEMENT',
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

            try {
                $this->notificationService?->sendPaymentSuccessNotification($order, $user);
            } catch (\Throwable $e) {
                Log::warning('Failed sending payment notification via MidtransService: ' . $e->getMessage());
            }

            return $order;
        });
    }
}
