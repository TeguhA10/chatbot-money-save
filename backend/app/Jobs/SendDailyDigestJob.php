<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\InsightsService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendDailyDigestJob
 *
 * Asynchronously generates and delivers personalized daily evening financial
 * summaries to individual WhatsApp users via queue worker.
 */
class SendDailyDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        public readonly string $userJid,
        public readonly ?string $date = null,
    ) {}

    public function handle(
        InsightsService $insightsService,
        WhatsAppNotificationService $whatsappService
    ): void {
        $user = User::where('jid', $this->userJid)->where('is_active', true)->first();

        if (!$user || !$insightsService->isDailyDigestEnabled($user)) {
            return;
        }

        $card = $insightsService->generateDailyDigestCard($user, $this->date);

        if (!$card) {
            return; // No transactions for today, skip digest
        }

        Log::info("Async Worker: Sending daily digest to {$user->jid}");

        $delivered = $whatsappService->sendMessage($user, $card);

        Log::info("Async Worker: Daily digest for {$user->jid} result: " . ($delivered ? 'delivered' : 'failed'));
    }
}
