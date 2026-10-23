<?php

namespace App\Jobs;

use App\Services\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendWhatsAppOtpJob
 *
 * Asynchronously delivers WhatsApp OTP messages via Queue Worker.
 * Offloads external Meta Graph API / Baileys Gateway HTTP round-trips
 * from the user login HTTP thread.
 */
class SendWhatsAppOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Timeout for the job execution in seconds.
     */
    public int $timeout = 30;

    /**
     * Backoff intervals (seconds) between retries.
     */
    public array $backoff = [2, 5, 10];

    public function __construct(
        public readonly string $phone,
        public readonly string $otp,
    ) {}

    public function handle(WhatsAppNotificationService $whatsappService): void
    {
        Log::info("Async Queue Worker: Dispatching WhatsApp OTP to {$this->phone}");

        $delivered = $whatsappService->sendOtp($this->phone, $this->otp);

        if (!$delivered) {
            Log::warning("Async Queue Worker: Failed to deliver OTP to {$this->phone}, retrying if attempts remain...");
            throw new \RuntimeException("Failed to deliver WhatsApp OTP to {$this->phone}");
        }

        Log::info("Async Queue Worker: WhatsApp OTP successfully delivered to {$this->phone}");
    }
}
