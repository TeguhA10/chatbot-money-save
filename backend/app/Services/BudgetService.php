<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class BudgetService
{
    /**
     * Upsert an overall monthly budget limit for a user.
     */
    public function setBudget(User $user, int $limitAmount, int $thresholdPercent = 80): Budget
    {
        if ($limitAmount <= 0) {
            throw new \InvalidArgumentException('Budget limit must be a positive integer.');
        }

        $encryptedLimit = app(EncryptionService::class)->encryptForStorage($limitAmount);

        $budget = Budget::updateOrCreate(
            [
                'user_jid' => $user->jid,
            ],
            [
                'encrypted_limit_amount'  => $encryptedLimit,
                'alert_threshold_percent' => $thresholdPercent,
            ]
        );

        return $budget;
    }

    /**
     * Get total expense for a user in the given month.
     */
    public function getMonthlyExpenseTotal(string $userJid, int $year, int $month): int
    {
        $total = Transaction::where('user_jid', $userJid)
            ->active()
            ->expenses()
            ->forMonth($year, $month)
            ->sum('amount');

        return (int) $total;
    }

    /**
     * Get consumption for a budget in the given or current month.
     *
     * @return array{
     *   budget: Budget,
     *   limit_amount: int,
     *   spent_amount: int,
     *   remaining_amount: int,
     *   percentage: float,
     *   is_warning: bool,
     *   is_exceeded: bool,
     *   progress_bar: string
     * }
     */
    public function getConsumption(Budget $budget, ?int $year = null, ?int $month = null): array
    {
        $now = Carbon::now();
        $year = $year ?? $now->year;
        $month = $month ?? $now->month;

        $spentAmount = $this->getMonthlyExpenseTotal($budget->user_jid, $year, $month);
        $limitAmount = $budget->limit_amount;
        $remainingAmount = max(0, $limitAmount - $spentAmount);
        $percentage = $limitAmount > 0 ? round(($spentAmount / $limitAmount) * 100, 1) : 0.0;
        $isWarning = $percentage >= $budget->alert_threshold_percent && $percentage < 100;
        $isExceeded = $spentAmount > $limitAmount;
        $progressBar = $this->renderProgressBar($percentage);

        return [
            'budget'           => $budget,
            'limit_amount'     => $limitAmount,
            'spent_amount'     => $spentAmount,
            'remaining_amount' => $remainingAmount,
            'percentage'       => $percentage,
            'is_warning'       => $isWarning,
            'is_exceeded'      => $isExceeded,
            'progress_bar'     => $progressBar,
        ];
    }

    /**
     * Render a 20-block Unicode progress bar.
     */
    public function renderProgressBar(float $percentage, int $length = 20): string
    {
        $clampedPercent = min(max($percentage, 0), 100);
        $filledBlocks = (int) round(($clampedPercent / 100) * $length);
        $emptyBlocks = $length - $filledBlocks;

        $bar = str_repeat('█', $filledBlocks) . str_repeat('░', $emptyBlocks);
        $suffix = ' ' . (int) round($percentage) . '%';

        if ($percentage > 100) {
            $suffix .= ' 🚨';
        } elseif ($percentage >= 80) {
            $suffix .= ' ⚠️';
        }

        return $bar . $suffix;
    }

    /**
     * Render the confirmation card when setting an overall monthly budget.
     */
    public function renderSetBudgetCard(Budget $budget): string
    {
        $consumption = $this->getConsumption($budget);
        $limitFormatted = 'Rp' . number_format($budget->limit_amount, 0, ',', '.');
        $spentFormatted = 'Rp' . number_format($consumption['spent_amount'], 0, ',', '.');
        $remainingFormatted = 'Rp' . number_format($consumption['remaining_amount'], 0, ',', '.');

        return "🎯 *Plafon Anggaran Bulanan Diatur*\n\n" .
               "• Limit Bulanan : {$limitFormatted}\n" .
               "• Terpakai      : {$spentFormatted} (" . (int) round($consumption['percentage']) . "%)\n" .
               "• Sisa Anggaran : {$remainingFormatted}\n\n" .
               "{$consumption['progress_bar']}";
    }

    /**
     * Render mini-card attached to an expense confirmation.
     */
    public function renderExpenseBudgetSnippet(User $user): ?string
    {
        $budget = Budget::where('user_jid', $user->jid)->first();

        if (!$budget) {
            return null;
        }

        $consumption = $this->getConsumption($budget);
        $spentFormatted = 'Rp' . number_format($consumption['spent_amount'], 0, ',', '.');
        $limitFormatted = 'Rp' . number_format($consumption['limit_amount'], 0, ',', '.');
        $remainingFormatted = 'Rp' . number_format($consumption['remaining_amount'], 0, ',', '.');

        $lines = [
            "🎯 *Status Budget Bulanan*",
            "Terpakai: {$spentFormatted} / {$limitFormatted} (Sisa: {$remainingFormatted})",
            $consumption['progress_bar']
        ];

        if ($consumption['is_exceeded']) {
            $over = $consumption['spent_amount'] - $consumption['limit_amount'];
            $overFormatted = 'Rp' . number_format($over, 0, ',', '.');
            $lines[] = "🚨 *Peringatan:* Pengeluaran Anda telah melebihi budget sebesar {$overFormatted}!";
        } elseif ($consumption['is_warning']) {
            $lines[] = "⚠️ *Perhatian:* Pengeluaran telah mencapai " . (int) round($consumption['percentage']) . "% dari plafon bulanan!";
        }

        return implode("\n", $lines);
    }

    /**
     * Render the overall budget overview card for the current month.
     */
    public function renderAllBudgetsCard(User $user): string
    {
        $budget = Budget::where('user_jid', $user->jid)->first();

        if (!$budget) {
            return "📊 *Ringkasan Budget Bulanan*\n\nBelum ada plafon anggaran yang diatur.\nKetik contoh: `budget 2jt` atau `anggaran 3.000.000`.";
        }

        $consumption = $this->getConsumption($budget);
        $spentFormatted = 'Rp' . number_format($consumption['spent_amount'], 0, ',', '.');
        $limitFormatted = 'Rp' . number_format($consumption['limit_amount'], 0, ',', '.');
        $remainingFormatted = 'Rp' . number_format($consumption['remaining_amount'], 0, ',', '.');
        $monthName = Carbon::now()->translatedFormat('F Y');

        return "📊 *Ringkasan Budget Bulan {$monthName}*\n\n" .
               "• Limit Anggaran: {$limitFormatted}\n" .
               "• Total Terpakai: {$spentFormatted}\n" .
               "• Sisa Anggaran : {$remainingFormatted}\n\n" .
               "{$consumption['progress_bar']}";
    }
}
