<?php

namespace App\Jobs;

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProcessIncomingWhatsAppMessage
 *
 * Asynchronous Worker Job for Decoupled Webhook Ingestion (Roadmap Phase 3).
 * Offloads message processing and ledger mutations from the HTTP ingestion thread.
 */
class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    public function __construct(
        public readonly string $source, // 'meta' or 'baileys'
        public readonly string $messageId,
        public readonly string $fromJid,
        public readonly string $pushName,
        public readonly string $messageText,
        public readonly ?string $fromPhone = null,
    ) {}

    public function handle(WebhookController $controller): void
    {
        Log::info("Async Queue Worker: Processing WhatsApp message", [
            'source'     => $this->source,
            'message_id' => $this->messageId,
            'from_jid'   => $this->fromJid,
        ]);

        try {
            $response = $controller->processMessage(
                $this->messageId,
                $this->fromJid,
                $this->pushName,
                $this->messageText
            );

            $data = $response->getData(true);

            if (($data['action'] ?? '') === 'IGNORE') {
                return;
            }

            // Dispatch outbound reply based on source
            if ($this->source === 'meta' && $this->fromPhone) {
                if (!empty($data['reply_text'])) {
                    $controller->sendMetaReply($this->fromPhone, $data['reply_text']);
                }
            } elseif ($this->source === 'baileys') {
                $this->sendBaileysReply($data);
            }
        } catch (\Throwable $e) {
            Log::error("Async Queue Worker: Error processing message {$this->messageId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Send outbound reply back to Baileys gateway via HTTP API.
     */
    protected function sendBaileysReply(array $data): void
    {
        $gatewayUrl = config('services.whatsapp.gateway_url', env('GATEWAY_OUTBOUND_URL', 'http://gateway:3001/send'));
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));

        $payload = [
            'jid'  => $this->fromJid,
            'text' => $data['reply_text'] ?? '',
            'type' => $data['action'] === 'SEND_DOCUMENT' ? 'DOCUMENT' : 'TEXT',
        ];

        if (!empty($data['file_url'])) {
            $payload['documentUrl'] = $data['file_url'];
            $payload['fileName'] = $data['file_name'] ?? 'Laporan_Keuangan.xlsx';
            $payload['mimetype'] = $data['mimetype'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        try {
            $req = Http::timeout(10);
            if ($secret) {
                $req->withHeader('X-Gateway-Secret', $secret);
            }
            $req->post($gatewayUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning("Async Queue Worker: Failed to deliver outbound reply to Baileys gateway", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
