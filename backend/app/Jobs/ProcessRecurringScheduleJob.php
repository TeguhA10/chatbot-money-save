<?php

namespace App\Jobs;

use App\Models\RecurringSchedule;
use App\Services\FinanceService;
use App\Services\RecurringService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcessRecurringScheduleJob
 *
 * Asynchronously processes individual due recurring commitments
 * (subscriptions, bills) via Queue Worker.
 */
class ProcessRecurringScheduleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly string $scheduleId,
    ) {}

    public function handle(FinanceService $financeService, RecurringService $recurringService): void
    {
        $schedule = RecurringSchedule::where('id', $this->scheduleId)
            ->where('is_active', true)
            ->with(['user', 'wallet'])
            ->first();

        if (!$schedule || !$schedule->user || !$schedule->user->is_active) {
            return;
        }

        DB::transaction(function () use ($schedule, $financeService, $recurringService) {
            $user = $schedule->user;
            $amount = $schedule->amount;
            $memo = "{$schedule->description} (Otomatis)";

            $financeService->recordTransaction($user, [
                'type'        => $schedule->type,
                'amount'      => $amount,
                'description' => $memo,
                'category_id' => $schedule->category_id,
                'wallet_id'   => $schedule->wallet_id,
            ]);

            // Advance to next cycle
            $currentRun = Carbon::parse($schedule->next_run_date);
            $nextRun = $recurringService->calculateNextRunDate((int) $schedule->day_of_month, $currentRun);

            $schedule->update([
                'last_run_at'   => now(),
                'next_run_date' => $nextRun->toDateString(),
            ]);

            Log::info("Async Worker: Successfully processed recurring schedule {$schedule->id} for {$user->jid}");
        });
    }
}
