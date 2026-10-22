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
     * Retrieve active/enabled payment channels from Midtrans merchant preferences.
     * Follows merchant settings directly from Midtrans portal instead of hardcoding.
     *
     * @return array<string> List of enabled channel IDs e.g. ['qris', 'gopay', 'bca_va', 'echannel', ...]
     */
    public function getEnabledPaymentChannels(): array
    {
        // 1. Explicit override via config/env if merchant explicitly configures e.g. MIDTRANS_ENABLED_PAYMENTS=qris,gopay
        $configured = config('services.midtrans.enabled_payments');
        if (!empty($configured) && is_array($configured)) {
            return array_values(array_filter(array_map('trim', array_map('strtolower', $configured))));
        }

        $serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY', ''));
        if (empty($serverKey)) {
            return [];
        }

        $isProduction = (bool) config('services.midtrans.is_production', false);
        $url = $isProduction
            ? 'https://app.midtrans.com/snap/v3/merchant-preferences'
            : 'https://app.sandbox.midtrans.com/snap/v3/merchant-preferences';

        $cacheKey = 'midtrans_enabled_channels_' . ($isProduction ? 'prod' : 'sandbox') . '_' . substr(md5($serverKey), 0, 8);

        try {
            return cache()->remember($cacheKey, now()->addMinutes(10), function () use ($url, $serverKey) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
                    'Accept'        => 'application/json',
                ])->timeout(5)->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    $channels = $data['payment_channels'] ?? [];
                    $enabled = [];

                    foreach ($channels as $channel) {
                        $name = $channel['name'] ?? '';
                        $isEnabled = !empty($channel['enabled']);
                        if ($isEnabled && $name) {
                            $enabled[] = strtolower($name);
                        }
                    }

                    if (!empty($enabled)) {
                        Log::info('Midtrans merchant enabled channels retrieved', [
                            'count'    => count($enabled),
                            'channels' => $enabled,
                        ]);
                        return $enabled;
                    }
                } else {
                    Log::warning('Midtrans merchant-preferences returned non-200: ' . $response->status(), [
                        'body' => $response->json(),
                    ]);
                }

                // If Midtrans preferences API cannot be read or no channels active in portal, return empty array
                return [];
            });
        } catch (\Throwable $e) {
            Log::warning('Midtrans merchant-preferences exception: ' . $e->getMessage());
            return [];
        }
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
     * Charge payment using Midtrans Core API (headless / custom UI).
     * Supports: qris, bca_va, bni_va, bri_va, mandiri_va, permata_va.
     */
    public function charge(PaymentOrder $order, string $method): array
    {
        $serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY', ''));
        $isProduction = (bool) config('services.midtrans.is_production', false);
        $coreApiUrl = $isProduction
            ? 'https://api.midtrans.com/v2/charge'
            : 'https://api.sandbox.midtrans.com/v2/charge';

        $method = strtolower($method);
        $user = $order->user;
        $phone = $user?->phone_number;
        if (!$phone && $user && str_ends_with($user->jid, '@s.whatsapp.net')) {
            $phone = explode('@', $user->jid)[0];
        }

        $basePayload = [
            'transaction_details' => [
                'order_id'     => $order->order_id,
                'gross_amount' => (int) $order->gross_amount,
            ],
            'item_details' => [
                [
                    'id'       => 'HEMATWOY-PRO',
                    'price'    => (int) $order->gross_amount,
                    'quantity' => 1,
                    'name'     => (int) $order->gross_amount >= 150000 ? 'HematWoy Pro 1 Tahun' : 'HematWoy Pro 30 Hari',
                ],
            ],
            'customer_details' => [
                'first_name' => $user?->display_name ?: 'Pengguna',
                'phone'      => $phone ?: '08123456789',
            ],
        ];

        switch ($method) {
            case 'qris':
                $basePayload['payment_type'] = 'qris';
                $basePayload['qris'] = ['acquirer' => 'gopay'];
                break;
            case 'bca_va':
                $basePayload['payment_type'] = 'bank_transfer';
                $basePayload['bank_transfer'] = ['bank' => 'bca'];
                break;
            case 'bni_va':
                $basePayload['payment_type'] = 'bank_transfer';
                $basePayload['bank_transfer'] = ['bank' => 'bni'];
                break;
            case 'bri_va':
                $basePayload['payment_type'] = 'bank_transfer';
                $basePayload['bank_transfer'] = ['bank' => 'bri'];
                break;
            case 'cimb_va':
                $basePayload['payment_type'] = 'bank_transfer';
                $basePayload['bank_transfer'] = ['bank' => 'cimb'];
                break;
            case 'mandiri_va':
            case 'echannel':
                $basePayload['payment_type'] = 'echannel';
                $basePayload['echannel'] = [
                    'bill_info1' => 'HematWoy Pro',
                    'bill_info2' => 'Langganan',
                ];
                break;
            case 'permata_va':
                $basePayload['payment_type'] = 'permata';
                break;
            default:
                throw new RuntimeException("Metode pembayaran '{$method}' tidak didukung.");
        }

        if (!empty($serverKey)) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])->timeout(10)->post($coreApiUrl, $basePayload);

                $data = $response->json();

                // If Midtrans returns 406 (e.g. order_id already used/charged), retry with attempt suffix
                if ($response->status() === 406 || ($data['status_code'] ?? '') === '406') {
                    $attemptOrderId = $order->order_id . '-ATTEMPT-' . substr(uniqid(), -4);
                    $basePayload['transaction_details']['order_id'] = $attemptOrderId;
                    $response = Http::withHeaders([
                        'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ])->timeout(10)->post($coreApiUrl, $basePayload);
                    $data = $response->json();
                }

                if ($response->successful()) {
                    $order->update([
                        'payment_type' => $method,
                    ]);

                    $result = [
                        'success'            => true,
                        'order_id'           => $order->order_id,
                        'payment_type'       => $method,
                        'gross_amount'       => (int) $order->gross_amount,
                        'transaction_status' => $data['transaction_status'] ?? 'pending',
                        'expiry_time'        => $data['expiry_time'] ?? now()->addMinutes(15)->format('Y-m-d H:i:s'),
                    ];

                    if ($method === 'qris') {
                        $qrUrl = null;
                        foreach ($data['actions'] ?? [] as $action) {
                            if (($action['name'] ?? '') === 'generate-qr-code') {
                                $qrUrl = $action['url'] ?? null;
                                break;
                            }
                        }
                        $qrString = $data['qr_string'] ?? null;
                        if (!$qrUrl && $qrString) {
                            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString);
                        }
                        $result['qr_url'] = $qrUrl;
                        $result['qr_string'] = $qrString;
                    }

                    if (isset($data['va_numbers'][0]['va_number'])) {
                        $result['va_number'] = $data['va_numbers'][0]['va_number'];
                        $result['bank'] = $data['va_numbers'][0]['bank'] ?? $method;
                    } elseif (isset($data['permata_va_number'])) {
                        $result['va_number'] = $data['permata_va_number'];
                        $result['bank'] = 'permata';
                    } elseif (isset($data['bill_key'])) {
                        $result['bill_key'] = $data['bill_key'];
                        $result['biller_code'] = $data['biller_code'] ?? '70012';
                        $result['bank'] = 'mandiri';
                    }

                    return $result;
                }

                Log::warning('Midtrans Core API Error Response', [
                    'status' => $response->status(),
                    'body'   => $data,
                ]);

                return [
                    'success'            => false,
                    'message'            => $data['status_message'] ?? 'Gagal memproses pembayaran via Midtrans.',
                    'order_id'           => $order->order_id,
                    'payment_type'       => $method,
                    'gross_amount'       => (int) $order->gross_amount,
                    'fallback_simulated' => !$isProduction,
                ];
            } catch (\Throwable $e) {
                Log::error('Midtrans Core API Exception: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Terjadi kendala saat menghubungi gateway pembayaran: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Server key Midtrans belum dikonfigurasi.',
        ];
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
            $rawOrderId = $payload['order_id'] ?? '';
            /** @var PaymentOrder $order */
            $order = PaymentOrder::whereKey($rawOrderId)->lockForUpdate()->first();
            if (!$order && str_contains($rawOrderId, '-ATTEMPT-')) {
                $baseOrderId = explode('-ATTEMPT-', $rawOrderId)[0];
                $order = PaymentOrder::whereKey($baseOrderId)->lockForUpdate()->first();
            }

            if (!$order) {
                throw new RuntimeException("PaymentOrder '{$rawOrderId}' not found.");
            }

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
