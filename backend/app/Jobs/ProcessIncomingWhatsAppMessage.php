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
        public string $messageText,
        public readonly ?string $fromPhone = null,
        public readonly bool $isVoiceNote = false,
        public readonly ?string $mediaId = null,
    ) {}

    public function handle(WebhookController $controller): void
    {
        Log::info("Async Queue Worker: Processing WhatsApp message", [
            'source'     => $this->source,
            'message_id' => $this->messageId,
            'from_jid'   => $this->fromJid,
            'is_voice'   => $this->isVoiceNote,
        ]);

        try {
            // Handle audio voice note download and offline transcription for Meta Cloud API
            if ($this->isVoiceNote && $this->mediaId && empty($this->messageText)) {
                $transcribed = $this->transcribeMetaAudio($this->mediaId);
                if (!empty($transcribed)) {
                    $this->messageText = $transcribed;
                } else {
                    if ($this->fromPhone) {
                        $controller->sendMetaReply(
                            $this->fromPhone,
                            "⚠️ Maaf, rekaman suara tidak terdengar jelas atau sistem transkripsi lokal sedang memproses antrean. Silakan coba rekam ulang lebih dekat atau kirim teks secara manual. 🙏"
                        );
                    }
                    return;
                }
            }

            $response = $controller->processMessage(
                $this->messageId,
                $this->fromJid,
                $this->pushName,
                $this->messageText
            );

            $data = $response->getData(true);

            if ($this->isVoiceNote && !empty($data['reply_text'])) {
                $data['reply_text'] = "🎤 _\"{$this->messageText}\"_\n\n" . $data['reply_text'];
            }

            if (($data['action'] ?? '') === 'IGNORE') {
                return;
            }

            // Dispatch outbound reply based on source
            if ($this->source === 'meta' && $this->fromPhone) {
                $sentInteractive = false;
                if (!empty($data['interactive'])) {
                    $sentInteractive = $controller->sendMetaInteractive($this->fromPhone, $data['interactive']);
                }
                if (!$sentInteractive && !empty($data['reply_text'])) {
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

    /**
     * Download audio media from Meta Cloud API and transcribe locally via voice-service.
     */
    protected function transcribeMetaAudio(string $mediaId): ?string
    {
        $version = config('services.whatsapp.api_version', env('WHATSAPP_API_VERSION', 'v21.0'));
        $accessToken = config('services.whatsapp.access_token', env('WHATSAPP_ACCESS_TOKEN'));
        $voiceServiceUrl = env('VOICE_SERVICE_URL', 'http://voice-service:9000/transcribe');

        if (!$accessToken) {
            Log::error("Voice STT: Missing WHATSAPP_ACCESS_TOKEN for downloading Meta media.");
            return null;
        }

        try {
            // 1. Get media URL from Meta Graph API
            $metaMediaResp = Http::withToken($accessToken)
                ->timeout(15)
                ->get("https://graph.facebook.com/{$version}/{$mediaId}");

            if ($metaMediaResp->failed()) {
                Log::error("Voice STT: Failed to retrieve media URL from Meta", [
                    'media_id' => $mediaId,
                    'status'   => $metaMediaResp->status(),
                    'body'     => $metaMediaResp->json(),
                ]);
                return null;
            }

            $mediaUrl = $metaMediaResp->json('url');
            if (!$mediaUrl) {
                return null;
            }

            // 2. Download binary audio stream from Meta CDN (requires Authorization header)
            $audioBinary = Http::withToken($accessToken)
                ->withHeaders(['User-Agent' => 'curl/7.68.0'])
                ->timeout(30)
                ->get($mediaUrl);

            if ($audioBinary->failed()) {
                Log::error("Voice STT: Failed to download audio stream from Meta", [
                    'status' => $audioBinary->status(),
                ]);
                return null;
            }

            // 3. Post audio stream to local faster-whisper container
            $transcribeResp = Http::timeout(30)
                ->attach('file', $audioBinary->body(), 'audio.ogg')
                ->post($voiceServiceUrl);

            if ($transcribeResp->successful()) {
                $text = trim($transcribeResp->json('text') ?? '');
                Log::info("Voice STT: Meta voice note successfully transcribed locally", ['text' => $text]);
                return !empty($text) ? $text : null;
            }

            Log::error("Voice STT: Voice service returned error", [
                'status' => $transcribeResp->status(),
                'body'   => $transcribeResp->body(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error("Voice STT: Exception during Meta audio transcription: " . $e->getMessage());
            return null;
        }
    }
}
