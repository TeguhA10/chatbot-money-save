<?php

namespace App\Services;

use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsAppNotificationService
 *
 * Dispatches outbound notifications (receipts, alerts) to WhatsApp users
 * via Meta WhatsApp Cloud API or Baileys Gateway.
 */
class WhatsAppNotificationService
{
    /**
     * Send official receipt notification when a payment order is settled.
     */
    public function sendPaymentSuccessNotification(PaymentOrder $order, User $user): void
    {
        $planName = (int) $order->gross_amount >= 150000 ? 'HematWoy Pro 1 Tahun' : 'HematWoy Pro 30 Hari';
        $formattedAmount = 'Rp' . number_format($order->gross_amount, 0, ',', '.');
        $expiryDate = $user->subscription_expires_at
            ? $user->subscription_expires_at->translatedFormat('d F Y')
            : now()->addDays((int) $order->gross_amount >= 150000 ? 365 : 30)->translatedFormat('d F Y');

        $receiptText = "🎉 *PEMBAYARAN BERHASIL — HEMATWOY PRO AKTIF!*\n"
            . "───────────────────────────\n"
            . "Terima kasih telah berlangganan *HematWoy Pro*!\n\n"
            . "📋 *Rincian Transaksi:*\n"
            . "• No. Order : *{$order->order_id}*\n"
            . "• Paket     : *{$planName}*\n"
            . "• Nominal   : *{$formattedAmount}*\n"
            . "• Status    : *Lunas (Settlement)*\n"
            . "• Aktif s/d : *{$expiryDate}*\n\n"
            . "✨ *Seluruh fitur Pro sudah aktif seketika:*\n"
            . "✓ Kuota pencatatan tanpa batas (Unlimited)\n"
            . "✓ Multi-dompet tanpa limit (BCA, Mandiri, GoPay, OVO, Cash)\n"
            . "✓ Download Laporan Excel lengkap\n"
            . "✓ Peringatan Plafon Anggaran (Anti-Boncos)\n\n"
            . "Selamat mengelola keuangan dengan rapi dan bebas boncos! 🚀";

        $this->sendMessage($user, $receiptText);
    }

    /**
     * Send text message to user via Meta Cloud API or Baileys Gateway.
     */
    public function sendMessage(User $user, string $text): void
    {
        $phone = $user->phone_number;
        if (!$phone && str_ends_with($user->jid, '@s.whatsapp.net')) {
            $phone = explode('@', $user->jid)[0];
        }

        // 1. Meta WhatsApp Cloud API (if configured)
        $phoneNumberId = config('services.whatsapp.phone_number_id', env('WHATSAPP_PHONE_NUMBER_ID'));
        $accessToken = config('services.whatsapp.access_token', env('WHATSAPP_ACCESS_TOKEN'));
        $version = config('services.whatsapp.api_version', env('WHATSAPP_API_VERSION', 'v21.0'));

        if ($phoneNumberId && $accessToken && $phone) {
            try {
                $cleanPhone = preg_replace('/\D/', '', $phone);
                $resp = Http::withToken($accessToken)
                    ->timeout(8)
                    ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'recipient_type'    => 'individual',
                        'to'                => $cleanPhone,
                        'type'              => 'text',
                        'text'              => [
                            'preview_url' => false,
                            'body'        => $text,
                        ],
                    ]);

                if ($resp->successful()) {
                    Log::info("WhatsAppNotificationService: Meta Cloud message delivered to {$cleanPhone}");
                } else {
                    Log::warning("WhatsAppNotificationService: Meta Cloud delivery failed", [
                        'status' => $resp->status(),
                        'body'   => $resp->json(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("WhatsAppNotificationService: Meta Cloud exception: " . $e->getMessage());
            }
        }

        // 2. Baileys gateway sidecar (if configured)
        $gatewayUrl = config('services.whatsapp.gateway_url', env('GATEWAY_OUTBOUND_URL', env('GATEWAY_URL', 'http://localhost:3001')));
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));

        $endpoint = str_ends_with($gatewayUrl, '/send') || str_ends_with($gatewayUrl, '/api/send')
            ? $gatewayUrl
            : rtrim($gatewayUrl, '/') . '/api/send';

        try {
            $req = Http::timeout(5);
            if ($secret) {
                $req->withHeader('X-Gateway-Secret', $secret);
            }
            $gatewayResp = $req->post($endpoint, [
                'jid'  => $user->jid,
                'text' => $text,
                'type' => 'TEXT',
            ]);

            if ($gatewayResp->successful()) {
                Log::info("WhatsAppNotificationService: Baileys gateway message delivered to {$user->jid}");
            }
        } catch (\Throwable $e) {
            Log::info("WhatsAppNotificationService: Gateway delivery skipped/failed: " . $e->getMessage());
        }
    }
}
