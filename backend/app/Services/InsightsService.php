<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InsightsService
{
    public function __construct(
        private readonly FinanceService $financeService,
    ) {}

    /**
     * Check if the user has opted in to the daily evening digest.
     */
    public function isDailyDigestEnabled(User $user): bool
    {
        return !Cache::get("user:digest:optout:{$user->jid}", false);
    }

    /**
     * Update user preference for the daily evening digest.
     */
    public function setDailyDigestEnabled(User $user, bool $enabled): void
    {
        if ($enabled) {
            Cache::forget("user:digest:optout:{$user->jid}");
        } else {
            Cache::forever("user:digest:optout:{$user->jid}", true);
        }
    }

    /**
     * Generate structured daily evening digest card for a user.
     */
    public function generateDailyDigestCard(User $user, ?string $date = null): ?string
    {
        $targetDate = $date ?? Carbon::now()->toDateString();

        $transactions = Transaction::where('user_jid', $user->jid)
            ->whereDate('transaction_date', $targetDate)
            ->active()
            ->get();

        if ($transactions->isEmpty()) {
            return null;
        }

        $totalIncome = 0;
        $totalExpense = 0;

        foreach ($transactions as $tx) {
            if ($tx->type === 'INCOME') {
                $totalIncome += $tx->amount;
            } elseif ($tx->type === 'EXPENSE') {
                $totalExpense += $tx->amount;
            }
        }

        $topExpenses = $transactions->where('type', 'EXPENSE')->sortByDesc('amount')->take(3);

        $net = $totalIncome - $totalExpense;
        $netSign = $net >= 0 ? '+' : '-';
        $netFmt = $netSign . 'Rp' . number_format(abs($net), 0, ',', '.');
        $incomeFmt = 'Rp' . number_format($totalIncome, 0, ',', '.');
        $expenseFmt = 'Rp' . number_format($totalExpense, 0, ',', '.');
        $balanceFmt = 'Rp' . number_format($user->current_balance, 0, ',', '.');

        $lines = [
            "📊 *Ringkasan Keuangan Hari Ini*\n",
            "Pemasukan: {$incomeFmt}",
            "Pengeluaran: {$expenseFmt}",
            "Net: {$netFmt}\n",
        ];

        if ($topExpenses->isNotEmpty()) {
            $lines[] = "Pengeluaran terbesar:";
            foreach ($topExpenses as $tx) {
                $amtFmt = 'Rp' . number_format($tx->amount, 0, ',', '.');
                $desc = $tx->description ?: 'Pengeluaran';
                $lines[] = "• {$desc} — {$amtFmt}";
            }
            $lines[] = "";
        }

        $lines[] = "Total Saldo: {$balanceFmt}";

        return implode("\n", $lines);
    }

    /**
     * Dispatch daily evening digests to all active users with activity today.
     */
    public function sendDailyDigests(): int
    {
        $today = Carbon::now()->toDateString();

        $activeUserJids = Transaction::whereDate('transaction_date', $today)
            ->active()
            ->distinct()
            ->pluck('user_jid');

        $sentCount = 0;
        foreach ($activeUserJids as $jid) {
            \App\Jobs\SendDailyDigestJob::dispatch($jid, $today);
            $sentCount++;
        }

        return $sentCount;
    }

    /**
     * Perform comparative spending variance analysis (Month-over-Month).
     *
     * @return array{
     *   current_spent: int,
     *   previous_spent: int,
     *   percentage_delta: float,
     *   is_higher: bool,
     *   top_increases: array<int, array{name: string, icon: string, delta: int}>,
     *   projection_to_eom: int
     * }
     */
    public function analyzeSpendingVariance(User $user, ?Carbon $date = null): array
    {
        $now = $date ?? Carbon::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;
        $currentDay = $now->day;
        $daysInMonth = $now->daysInMonth;

        $lastMonthDate = $now->copy()->subMonthNoOverflow();
        $lastYear = $lastMonthDate->year;
        $lastMonth = $lastMonthDate->month;

        // Current month expenses
        $currentSpent = (int) Transaction::where('user_jid', $user->jid)
            ->forMonth($currentYear, $currentMonth)
            ->active()
            ->expenses()
            ->sum('amount');

        // Previous month expenses
        $lastSpent = (int) Transaction::where('user_jid', $user->jid)
            ->forMonth($lastYear, $lastMonth)
            ->active()
            ->expenses()
            ->sum('amount');

        $percentageDelta = 0.0;
        if ($lastSpent > 0) {
            $percentageDelta = round((($currentSpent - $lastSpent) / $lastSpent) * 100, 1);
        } elseif ($currentSpent > 0) {
            $percentageDelta = 100.0;
        }

        // Projection
        $dailyBurnRate = $currentDay > 0 ? ($currentSpent / $currentDay) : 0;
        $projection = (int) round($dailyBurnRate * $daysInMonth);

        return [
            'current_spent'     => $currentSpent,
            'previous_spent'    => $lastSpent,
            'percentage_delta'  => abs($percentageDelta),
            'is_higher'         => $percentageDelta >= 0,
            'top_increases'     => [],
            'projection_to_eom' => $projection,
        ];
    }

    /**
     * Render Month-over-Month variance analysis reply card.
     */
    public function renderVarianceAnalysisCard(User $user): string
    {
        $analysis = $this->analyzeSpendingVariance($user);

        $deltaFormatted = (int) round($analysis['percentage_delta']) . '%';
        $direction = $analysis['is_higher'] ? 'naik' : 'turun';

        $lines = [
            "📊 *Analisis Keuangan Bulan Ini*",
            "Dibandingkan bulan lalu, pengeluaran Anda {$direction} *{$deltaFormatted}*.\n",
        ];

        if (!empty($analysis['top_increases'])) {
            $lines[] = "Peningkatan terbesar berasal dari:";
            foreach ($analysis['top_increases'] as $item) {
                $fmt = '+Rp' . number_format($item['delta'], 0, ',', '.');
                $lines[] = "{$item['icon']} {$item['name']}: {$fmt}";
            }
            $lines[] = "";
        }

        $projFmt = 'Rp' . number_format($analysis['projection_to_eom'], 0, ',', '.');
        $lines[] = "Estimasi pengeluaran sampai akhir bulan: sekitar {$projFmt}.";

        return implode("\n", $lines);
    }
}
