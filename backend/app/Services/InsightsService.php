<?php

namespace App\Services;

use App\Models\Category;
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
            ->with('category')
            ->get();

        if ($transactions->isEmpty()) {
            return null;
        }

        $totalIncome = 0;
        $totalExpense = 0;
        $categoryTotals = [];

        foreach ($transactions as $tx) {
            if ($tx->type === 'INCOME') {
                $totalIncome += $tx->amount;
            } elseif ($tx->type === 'EXPENSE') {
                $totalExpense += $tx->amount;

                $catName = $tx->category ? $tx->category->name : 'Lainnya';
                $icon    = $tx->category ? $tx->category->icon : '📦';
                $key     = "{$icon} {$catName}";

                $categoryTotals[$key] = ($categoryTotals[$key] ?? 0) + $tx->amount;
            }
        }

        arsort($categoryTotals);
        $topCategories = array_slice($categoryTotals, 0, 3, true);

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

        if (!empty($topCategories)) {
            $lines[] = "Pengeluaran terbesar:";
            foreach ($topCategories as $name => $amount) {
                $amtFmt = 'Rp' . number_format($amount, 0, ',', '.');
                $lines[] = "{$name} — {$amtFmt}";
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
        $gatewayUrl = config('services.gateway.url', 'http://127.0.0.1:3000');

        foreach ($activeUserJids as $jid) {
            $user = User::where('jid', $jid)->where('is_active', true)->first();
            if (!$user || !$this->isDailyDigestEnabled($user)) {
                continue;
            }

            $card = $this->generateDailyDigestCard($user, $today);
            if (!$card) {
                continue;
            }

            // Attempt sending via gateway sidecar
            try {
                Http::timeout(3)->post("{$gatewayUrl}/send-message", [
                    'jid'     => $user->jid,
                    'message' => $card,
                ]);
            } catch (\Throwable) {
                // Non-fatal if gateway is currently offline
            }

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
        $currentTxs = Transaction::where('user_jid', $user->jid)
            ->forMonth($currentYear, $currentMonth)
            ->active()
            ->expenses()
            ->with('category')
            ->get();

        $currentSpent = 0;
        $currentCatTotals = [];
        foreach ($currentTxs as $tx) {
            $currentSpent += $tx->amount;
            $catId = $tx->category_id ?? 'uncategorized';
            $currentCatTotals[$catId] = ($currentCatTotals[$catId] ?? 0) + $tx->amount;
        }

        // Previous month expenses
        $lastTxs = Transaction::where('user_jid', $user->jid)
            ->forMonth($lastYear, $lastMonth)
            ->active()
            ->expenses()
            ->with('category')
            ->get();

        $lastSpent = 0;
        $lastCatTotals = [];
        foreach ($lastTxs as $tx) {
            $lastSpent += $tx->amount;
            $catId = $tx->category_id ?? 'uncategorized';
            $lastCatTotals[$catId] = ($lastCatTotals[$catId] ?? 0) + $tx->amount;
        }

        $percentageDelta = 0.0;
        if ($lastSpent > 0) {
            $percentageDelta = round((($currentSpent - $lastSpent) / $lastSpent) * 100, 1);
        } elseif ($currentSpent > 0) {
            $percentageDelta = 100.0;
        }

        // Top category increases
        $increases = [];
        foreach ($currentCatTotals as $catId => $amt) {
            $prevAmt = $lastCatTotals[$catId] ?? 0;
            $delta = $amt - $prevAmt;
            if ($delta > 0) {
                $category = $catId !== 'uncategorized' ? Category::find($catId) : null;
                $increases[] = [
                    'name'  => $category ? $category->name : 'Lainnya',
                    'icon'  => $category ? $category->icon : '📦',
                    'delta' => $delta,
                ];
            }
        }

        usort($increases, fn($a, $b) => $b['delta'] - $a['delta']);
        $topIncreases = array_slice($increases, 0, 3);

        // Projection
        $dailyBurnRate = $currentDay > 0 ? ($currentSpent / $currentDay) : 0;
        $projection = (int) round($dailyBurnRate * $daysInMonth);

        return [
            'current_spent'     => $currentSpent,
            'previous_spent'    => $lastSpent,
            'percentage_delta'  => abs($percentageDelta),
            'is_higher'         => $percentageDelta >= 0,
            'top_increases'     => $topIncreases,
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
