<?php

namespace App\Jobs;

use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendPaymentSuccessNotificationJob
 *
 * Asynchronously sends official Pro subscription payment receipts to WhatsApp
 * via Queue Worker, preventing latency and timeouts on payment webhook threads.
 */
class SendPaymentSuccessNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [3, 10, 30];

    public function __construct(
        public readonly string $orderId,
        public readonly string $userJid,
    ) {}

    public function handle(WhatsAppNotificationService $notificationService): void
    {
        Log::info("Async Worker: Sending payment success notification for order {$this->orderId} to {$this->userJid}");

        $order = PaymentOrder::where('order_id', $this->orderId)->first();
        $user  = User::where('jid', $this->userJid)->first();

        if (!$order || !$user) {
            Log::warning("Async Worker: Order {$this->orderId} or User {$this->userJid} not found, skipping receipt.");
            return;
        }

        $notificationService->sendPaymentSuccessNotification($order, $user);

        Log::info("Async Worker: Payment success notification dispatched for order {$this->orderId}");
    }
}
