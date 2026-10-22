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
    public function sendMessage(User $user, string $text): bool
    {
        $target = $user->phone_number ?: $user->jid;
        return $this->sendRawMessage($target, $text);
    }

    /**
     * Send OTP verification message directly to a phone number or JID.
     */
    public function sendOtp(string $phoneOrJid, string $otp): bool
    {
        $messageText = "🔐 *KODE LOGIN HEMATWOY*\n\n"
            . "Kode OTP Anda: *{$otp}*\n\n"
            . "⚠️ Berlaku selama 5 menit. Jangan bagikan kode ini kepada siapapun demi keamanan data Anda.";

        return $this->sendRawMessage($phoneOrJid, $messageText);
    }

    /**
     * Send raw text message to a phone number or JID via Meta Cloud API or Baileys Gateway.
     */
    public function sendRawMessage(string $phoneOrJid, string $text): bool
    {
        $cleanPhone = preg_replace('/\D/', '', $phoneOrJid);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }
        $jid = str_contains($phoneOrJid, '@') ? $phoneOrJid : ($cleanPhone . '@s.whatsapp.net');

        $delivered = false;

        // 1. Meta WhatsApp Cloud API (Primary channel when configured)
        $phoneNumberId = config('services.whatsapp.phone_number_id', env('WHATSAPP_PHONE_NUMBER_ID'));
        $accessToken = config('services.whatsapp.access_token', env('WHATSAPP_ACCESS_TOKEN'));
        $version = config('services.whatsapp.api_version', env('WHATSAPP_API_VERSION', 'v21.0'));

        if ($phoneNumberId && $accessToken && $cleanPhone) {
            try {
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
                    $delivered = true;
                } else {
                    Log::warning("WhatsAppNotificationService: Meta Cloud delivery failed", [
                        'phone'  => $cleanPhone,
                        'status' => $resp->status(),
                        'body'   => $resp->json(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("WhatsAppNotificationService: Meta Cloud exception: " . $e->getMessage());
            }
        }

        // If successfully delivered via Meta Cloud API, no need to duplicate on Baileys
        if ($delivered) {
            return true;
        }

        // 2. Baileys Gateway Sidecar (Secondary / Fallback channel)
        $gatewayUrl = config('services.whatsapp.gateway_url', env('GATEWAY_OUTBOUND_URL', env('GATEWAY_URL', env('WHATSAPP_GATEWAY_URL', 'http://gateway:3001'))));
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));

        $endpoint = (str_ends_with($gatewayUrl, '/send') || str_ends_with($gatewayUrl, '/api/send'))
            ? $gatewayUrl
            : rtrim($gatewayUrl, '/') . '/api/send';

        try {
            $req = Http::timeout(4);
            if ($secret) {
                $req->withHeaders(['X-Gateway-Secret' => $secret]);
            }
            $gatewayResp = $req->post($endpoint, [
                'jid'  => $jid,
                'text' => $text,
                'type' => 'TEXT',
            ]);

            if ($gatewayResp->successful()) {
                Log::info("WhatsAppNotificationService: Baileys gateway message delivered to {$jid}");
                $delivered = true;
            } else {
                Log::warning("WhatsAppNotificationService: Baileys gateway delivery failed", [
                    'jid'    => $jid,
                    'status' => $gatewayResp->status(),
                    'body'   => $gatewayResp->json(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::info("WhatsAppNotificationService: Gateway delivery skipped/failed: " . $e->getMessage());

            // Local dev fallback if 'gateway' container name is unresolvable on host machine
            if (str_contains($endpoint, 'gateway:3001')) {
                $fallbackEndpoint = str_replace('gateway:3001', '127.0.0.1:3001', $endpoint);
                try {
                    $req = Http::timeout(3);
                    if ($secret) {
                        $req->withHeaders(['X-Gateway-Secret' => $secret]);
                    }
                    $fallbackResp = $req->post($fallbackEndpoint, [
                        'jid'  => $jid,
                        'text' => $text,
                        'type' => 'TEXT',
                    ]);
                    if ($fallbackResp->successful()) {
                        Log::info("WhatsAppNotificationService: Baileys localhost fallback delivered to {$jid}");
                        $delivered = true;
                    }
                } catch (\Throwable) {}
            }
        }

        return $delivered;
    }
}
