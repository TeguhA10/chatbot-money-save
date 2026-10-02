<?php

namespace App\Services;

use App\Models\RecurringSchedule;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringService
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly EncryptionService $encryption,
    ) {}

    /**
     * Create a new recurring schedule.
     */
    public function createSchedule(User $user, array $data): RecurringSchedule
    {
        $amount = (int) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Recurring amount must be a positive integer.');
        }

        $dayOfMonth = (int) ($data['day_of_month'] ?? 1);
        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            $dayOfMonth = 1;
        }

        $nextRunDate = $data['next_run_date'] ?? $this->calculateNextRunDate($dayOfMonth)->toDateString();

        $schedule = RecurringSchedule::create([
            'user_jid'         => $user->jid,
            'wallet_id'        => $data['wallet_id'] ?? null,
            'category_id'      => $data['category_id'] ?? null,
            'type'             => $data['type'] ?? 'EXPENSE',
            'encrypted_amount' => $this->encryption->encryptForStorage($amount),
            'description'      => $data['description'] ?? 'Langganan',
            'frequency'        => $data['frequency'] ?? 'MONTHLY',
            'day_of_month'     => $dayOfMonth,
            'next_run_date'    => $nextRunDate,
            'is_active'        => true,
        ]);

        return $schedule;
    }

    /**
     * Calculate the next execution date for a monthly schedule.
     */
    public function calculateNextRunDate(int $dayOfMonth, ?Carbon $fromDate = null): Carbon
    {
        $base = $fromDate ? $fromDate->copy() : Carbon::now();
        $targetDay = min($dayOfMonth, $base->daysInMonth);
        $candidate = $base->copy()->day($targetDay);

        if ($candidate->isPast() || $candidate->isToday()) {
            $nextMonth = $base->copy()->addMonthNoOverflow();
            $targetDayNext = min($dayOfMonth, $nextMonth->daysInMonth);
            $candidate = $nextMonth->day($targetDayNext);
        }

        return $candidate->startOfDay();
    }

    /**
     * List active schedules for a user.
     */
    public function listSchedules(User $user): Collection
    {
        return RecurringSchedule::where('user_jid', $user->jid)
            ->where('is_active', true)
            ->with(['wallet', 'category'])
            ->orderBy('day_of_month')
            ->get();
    }

    /**
     * Cancel an active schedule by ID or description.
     */
    public function cancelSchedule(User $user, string $identifier): ?RecurringSchedule
    {
        $clean = trim($identifier);

        $isUuid = \Illuminate\Support\Str::isUuid($clean);
        $schedule = RecurringSchedule::where('user_jid', $user->jid)
            ->where('is_active', true)
            ->where(function ($q) use ($clean, $isUuid) {
                if ($isUuid) {
                    $q->where('id', $clean)
                      ->orWhereRaw('LOWER(description) = ?', [strtolower($clean)]);
                } else {
                    $q->whereRaw('LOWER(description) = ?', [strtolower($clean)]);
                }
            })
            ->first();

        if ($schedule) {
            $schedule->update(['is_active' => false]);
            return $schedule;
        }

        return null;
    }

    /**
     * Render active schedules card for WhatsApp.
     */
    public function renderSchedulesCard(User $user): string
    {
        $schedules = $this->listSchedules($user);

        if ($schedules->isEmpty()) {
            return "⏰ *Daftar Transaksi Rutin*\n\nBelum ada transaksi rutin atau langganan aktif.\n\nContoh: `langganan Netflix 186rb setiap tanggal 5 via bca`";
        }

        $lines = ["⏰ *Daftar Transaksi Rutin & Langganan*\n"];

        foreach ($schedules as $s) {
            $typeEmoji = $s->type === 'INCOME' ? '📥' : '📤';
            $walletName = $s->wallet ? $s->wallet->name : 'Cash';
            $amountFormatted = 'Rp' . number_format($s->amount, 0, ',', '.');
            $nextDate = Carbon::parse($s->next_run_date)->format('d M Y');

            $lines[] = "{$typeEmoji} *{$s->description}* — {$amountFormatted}\n" .
                       "   • Jadwal: Tanggal {$s->day_of_month} tiap bulan\n" .
                       "   • Dompet: {$walletName}\n" .
                       "   • Berikutnya: {$nextDate}";
        }

        $lines[] = "\n_Ketik \"hapus langganan <nama>\" untuk membatalkan._";

        return implode("\n", $lines);
    }

    /**
     * Process all schedules that are due on or before $date.
     *
     * @return array<int, array{schedule_id: string, user_jid: string, description: string, amount: int}>
     */
    public function processDueSchedules(?Carbon $date = null): array
    {
        $targetDate = ($date ?? Carbon::now())->toDateString();

        $dueSchedules = RecurringSchedule::where('is_active', true)
            ->where('next_run_date', '<=', $targetDate)
            ->with(['user', 'wallet'])
            ->get();

        $processed = [];

        foreach ($dueSchedules as $schedule) {
            try {
                DB::transaction(function () use ($schedule, &$processed) {
                    $user = $schedule->user;
                    if (!$user || !$user->is_active) {
                        return;
                    }

                    $amount = $schedule->amount;
                    $memo = "{$schedule->description} (Otomatis)";

                    $this->financeService->recordTransaction($user, [
                        'type'        => $schedule->type,
                        'amount'      => $amount,
                        'description' => $memo,
                        'category_id' => $schedule->category_id,
                        'wallet_id'   => $schedule->wallet_id,
                    ]);

                    // Advance to next cycle
                    $currentRun = Carbon::parse($schedule->next_run_date);
                    $nextRun = $this->calculateNextRunDate((int) $schedule->day_of_month, $currentRun);

                    $schedule->update([
                        'last_run_at'   => now(),
                        'next_run_date' => $nextRun->toDateString(),
                    ]);

                    $processed[] = [
                        'schedule_id' => $schedule->id,
                        'user_jid'    => $user->jid,
                        'description' => $schedule->description,
                        'amount'      => $amount,
                    ];
                });
            } catch (\Throwable $e) {
                Log::error('RecurringService: Failed processing schedule', [
                    'schedule_id' => $schedule->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }
}
