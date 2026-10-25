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

        // Early idempotency check to prevent duplicate execution across retries
        if (\App\Models\ProcessedMessage::where('message_id', $this->messageId)->exists()) {
            Log::info("Async Queue Worker: Duplicate message skipped early", ['message_id' => $this->messageId]);
            return;
        }

        try {
            // Handle audio voice note download and transcription for Meta Cloud API
            if ($this->isVoiceNote && $this->mediaId && empty($this->messageText)) {
                $transcription = $this->transcribeMetaAudio($this->mediaId);
                if (!empty($transcription['text'])) {
                    $this->messageText = $transcription['text'];
                } else {
                    if ($this->fromPhone) {
                        $errorMsg = ($transcription['error_reason'] ?? '') === 'NO_STT_CONFIGURED'
                            ? "⚠️ *Layanan Voice Note Belum Aktif*\n\n" .
                              "Sistem belum dapat mentranskripsi rekaman suara karena layanan Speech-to-Text belum terhubung.\n\n" .
                              "💡 *Cara Aktivasi Mudah & Gratis:*\n" .
                              "1. Dapatkan API Key gratis di *console.groq.com*\n" .
                              "2. Masukkan ke file `.env`:\n" .
                              "   `GROQ_API_KEY=gsk_...`\n" .
                              "3. Atau jalankan voice-service lokal: `docker compose up voice-service -d`\n\n" .
                              "Untuk saat ini, silakan ketik catatan keuanganmu lewat chat teks ya! 😊"
                            : "⚠️ Maaf, rekaman suara tidak terdengar jelas atau durasinya terlalu singkat. Silakan coba rekam ulang lebih dekat atau kirim teks secara manual. 🙏";

                        $controller->sendMetaReply($this->fromPhone, $errorMsg);
                    }
                    return;
                }
            }

            $response = $controller->processMessage(
                $this->messageId,
                $this->fromJid,
                $this->pushName,
                $this->messageText,
                $this->isVoiceNote
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
     * Download audio media from Meta Cloud API and transcribe via Groq, OpenAI, Gemini, or local whisper.
     *
     * @return array{text: ?string, provider?: string, error_reason?: string}
     */
    protected function transcribeMetaAudio(string $mediaId): array
    {
        $version = config('services.whatsapp.api_version', env('WHATSAPP_API_VERSION', 'v21.0'));
        $accessToken = config('services.whatsapp.access_token', env('WHATSAPP_ACCESS_TOKEN'));
        $user = \App\Models\User::where('jid', $this->fromJid)->first();
        $userEntities = '';
        if ($user) {
            $wNames = $user->wallets()->pluck('name')->implode(', ');
            $gNames = $user->financialGoals()->where('status', 'ACTIVE')->pluck('name')->implode(', ');
            $userEntities = trim("{$wNames}, {$gNames}", ', ');
        }
        $entityPrompt = !empty($userEntities) ? "Dompet & Target: {$userEntities}. " : "";
        $financialPrompt = "Catatan keuangan: {$entityPrompt}SeaBank, BCA, Mandiri, BRI, BNI, GoPay, OVO, DANA, ShopeePay, Cash, ceban, goceng, gocap, seceng, rebu, keluar 25000 makan siang, beli bensin 15rb, transfer 500rb, gaji 5jt, bayar pulsa 50rb.";

        if (!$accessToken) {
            Log::error("Voice STT: Missing WHATSAPP_ACCESS_TOKEN for downloading Meta media.");
            return ['text' => null, 'error_reason' => 'MISSING_ACCESS_TOKEN'];
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
                return ['text' => null, 'error_reason' => 'META_MEDIA_URL_FAILED'];
            }

            $mediaUrl = $metaMediaResp->json('url');
            if (!$mediaUrl) {
                return ['text' => null, 'error_reason' => 'EMPTY_MEDIA_URL'];
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
                return ['text' => null, 'error_reason' => 'DOWNLOAD_FAILED'];
            }

            $audioBytes = $audioBinary->body();

            // A. Try Groq Whisper API (Fastest & Free)
            $groqKey = config('services.groq.key', env('GROQ_API_KEY'));
            if ($groqKey) {
                try {
                    $groqResp = Http::withToken($groqKey)
                        ->timeout(15)
                        ->attach('file', $audioBytes, 'voice.ogg')
                        ->post('https://api.groq.com/openai/v1/audio/transcriptions', [
                            'model'    => config('services.groq.whisper_model', env('GROQ_WHISPER_MODEL', 'whisper-large-v3')),
                            'language' => 'id',
                            'prompt'   => $financialPrompt,
                        ]);

                    if ($groqResp->successful()) {
                        $text = trim($groqResp->json('text') ?? '');
                        if (!empty($text)) {
                            Log::info("Voice STT: Transcribed via Groq Whisper", ['text' => $text]);
                            return ['text' => $text, 'provider' => 'Groq Whisper'];
                        }
                    } else {
                        Log::warning("Voice STT: Groq API failed", ['body' => $groqResp->json()]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Voice STT: Groq API exception: " . $e->getMessage());
                }
            }

            // B. Try OpenAI Whisper API
            $openaiKey = config('services.openai.key', env('OPENAI_API_KEY'));
            if ($openaiKey) {
                try {
                    $openaiResp = Http::withToken($openaiKey)
                        ->timeout(20)
                        ->attach('file', $audioBytes, 'voice.ogg')
                        ->post('https://api.openai.com/v1/audio/transcriptions', [
                            'model'    => 'whisper-1',
                            'language' => 'id',
                            'prompt'   => $financialPrompt,
                        ]);

                    if ($openaiResp->successful()) {
                        $text = trim($openaiResp->json('text') ?? '');
                        if (!empty($text)) {
                            Log::info("Voice STT: Transcribed via OpenAI Whisper", ['text' => $text]);
                            return ['text' => $text, 'provider' => 'OpenAI Whisper'];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("Voice STT: OpenAI Whisper exception: " . $e->getMessage());
                }
            }

            // C. Try Google Gemini API
            $geminiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
            if ($geminiKey) {
                try {
                    $model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-lite-latest'));
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}";
                    $geminiResp = Http::timeout(20)->post($url, [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'inlineData' => [
                                            'mimeType' => 'audio/ogg',
                                            'data'     => base64_encode($audioBytes),
                                        ],
                                    ],
                                    [
                                        'text' => 'Dengarkan rekaman suara ini dengan teliti. Transkripsikan apa yang diucapkan dalam bahasa Indonesia persis apa adanya untuk pencatatan keuangan (contoh: "keluar 25rb makan siang", "beli bensin 15000", "+500rb bonus gaji"). Tuliskan HANYA teks transkripsi ucapannya saja tanpa tanda petik, tanpa penjelasan pengantar, dan tanpa penutup.',
                                    ],
                                ],
                            ],
                        ],
                    ]);

                    if ($geminiResp->successful()) {
                        $rawText = trim($geminiResp->json('candidates.0.content.parts.0.text') ?? '');
                        $cleaned = trim(trim($rawText, '"\'`'));
                        if (!empty($cleaned)) {
                            Log::info("Voice STT: Transcribed via Gemini", ['text' => $cleaned]);
                            return ['text' => $cleaned, 'provider' => 'Google Gemini'];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("Voice STT: Gemini STT exception: " . $e->getMessage());
                }
            }

            // D. Try Local faster-whisper container candidates
            $candidateUrls = array_unique([
                env('VOICE_SERVICE_URL', 'http://voice-service:9000/transcribe'),
                'http://127.0.0.1:9000/transcribe',
                'http://localhost:9000/transcribe',
            ]);

            foreach ($candidateUrls as $url) {
                try {
                    $transcribeResp = Http::timeout(8)
                        ->attach('file', $audioBytes, 'audio.ogg')
                        ->post($url);

                    if ($transcribeResp->successful()) {
                        $text = trim($transcribeResp->json('text') ?? '');
                        if (!empty($text)) {
                            Log::info("Voice STT: Transcribed locally via {$url}", ['text' => $text]);
                            return ['text' => $text, 'provider' => 'Local Voice'];
                        }
                    }
                } catch (\Throwable) {
                    // Try next candidate
                }
            }

            $hasAnyKey = (bool) ($groqKey || $openaiKey || $geminiKey);
            return [
                'text'         => null,
                'error_reason' => $hasAnyKey ? 'STT_EMPTY_RESULT' : 'NO_STT_CONFIGURED',
            ];
        } catch (\Throwable $e) {
            Log::error("Voice STT: Exception during Meta audio transcription: " . $e->getMessage());
            return ['text' => null, 'error_reason' => 'EXCEPTION'];
        }
    }
}
