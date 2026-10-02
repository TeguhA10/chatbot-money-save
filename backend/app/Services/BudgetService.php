<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class BudgetService
{
    /**
     * Upsert a static monthly budget rule for a user's category.
     */
    public function setBudget(User $user, string $categoryId, int $limitAmount, int $thresholdPercent = 80): Budget
    {
        if ($limitAmount <= 0) {
            throw new \InvalidArgumentException('Budget limit must be a positive integer.');
        }

        $encryptedLimit = app(EncryptionService::class)->encryptForStorage($limitAmount);

        $budget = Budget::updateOrCreate(
            [
                'user_jid'    => $user->jid,
                'category_id' => $categoryId,
            ],
            [
                'encrypted_limit_amount'  => $encryptedLimit,
                'alert_threshold_percent' => $thresholdPercent,
            ]
        );

        return $budget;
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

        $transactions = Transaction::where('user_jid', $budget->user_jid)
            ->where('category_id', $budget->category_id)
            ->active()
            ->expenses()
            ->forMonth($year, $month)
            ->get();

        $spentAmount = 0;
        foreach ($transactions as $tx) {
            $spentAmount += $tx->amount;
        }

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
     * Render the confirmation card when setting a budget.
     */
    public function renderSetBudgetCard(Budget $budget): string
    {
        $category = $budget->category;
        $catName = $category ? ($category->icon . ' ' . $category->name) : 'Kategori';
        $consumption = $this->getConsumption($budget);

        $limitFormatted = 'Rp' . number_format($budget->limit_amount, 0, ',', '.');
        $spentFormatted = 'Rp' . number_format($consumption['spent_amount'], 0, ',', '.');

        return "🎯 *Plafon Anggaran Diatur*\n" .
               "Kategori: {$catName}\n" .
               "Limit Bulanan: {$limitFormatted}\n" .
               "Status Saat Ini: {$spentFormatted} / {$limitFormatted} (" . (int) round($consumption['percentage']) . "%)\n" .
               "{$consumption['progress_bar']}";
    }

    /**
     * Render mini-card attached to an expense confirmation.
     */
    public function renderExpenseBudgetSnippet(User $user, string $categoryId): ?string
    {
        $budget = Budget::where('user_jid', $user->jid)
            ->where('category_id', $categoryId)
            ->first();

        if (!$budget) {
            return null;
        }

        $consumption = $this->getConsumption($budget);
        $category = $budget->category;
        $catName = $category ? $category->name : 'Kategori';
        $icon = $category ? $category->icon : '📌';

        $spentFormatted = 'Rp' . number_format($consumption['spent_amount'], 0, ',', '.');
        $limitFormatted = 'Rp' . number_format($consumption['limit_amount'], 0, ',', '.');
        $remainingFormatted = 'Rp' . number_format($consumption['remaining_amount'], 0, ',', '.');

        $lines = [
            "{$icon} *Budget {$catName}*",
            "Terpakai: {$spentFormatted} / {$limitFormatted}",
            "Sisa: {$remainingFormatted}",
            $consumption['progress_bar']
        ];

        if ($consumption['is_exceeded']) {
            $over = $consumption['spent_amount'] - $consumption['limit_amount'];
            $overFormatted = 'Rp' . number_format($over, 0, ',', '.');
            $lines[] = "⚠️ *Peringatan:* Anda telah melebihi budget sebesar {$overFormatted}!";
        } elseif ($consumption['is_warning']) {
            $lines[] = "⚠️ *Perhatian:* Pengeluaran telah mencapai " . (int) round($consumption['percentage']) . "% dari plafon!";
        }

        return implode("\n", $lines);
    }

    /**
     * Render the all-budgets overview card for the current month.
     */
    public function renderAllBudgetsCard(User $user): string
    {
        $budgets = Budget::where('user_jid', $user->jid)->with('category')->get();

        if ($budgets->isEmpty()) {
            return "📊 *Ringkasan Budget*\n\nBelum ada plafon anggaran yang diatur.\nKetik contoh: `budget makan 1jt` atau `budget transport 500rb`.";
        }

        $totalBudgeted = 0;
        $totalSpent = 0;
        $sections = [];

        foreach ($budgets as $budget) {
            $consumption = $this->getConsumption($budget);
            $totalBudgeted += $consumption['limit_amount'];
            $totalSpent += $consumption['spent_amount'];

            $category = $budget->category;
            $catName = $category ? ($category->icon . ' ' . $category->name) : 'Kategori';

            $spentFormatted = 'Rp' . number_format($consumption['spent_amount'], 0, ',', '.');
            $limitFormatted = 'Rp' . number_format($consumption['limit_amount'], 0, ',', '.');
            $remainingFormatted = 'Rp' . number_format($consumption['remaining_amount'], 0, ',', '.');

            $sections[] = "{$catName}\n" .
                          "{$spentFormatted} / {$limitFormatted} (Sisa: {$remainingFormatted})\n" .
                          $consumption['progress_bar'];
        }

        $totalPercent = $totalBudgeted > 0 ? (int) round(($totalSpent / $totalBudgeted) * 100) : 0;
        $totalSpentFormatted = 'Rp' . number_format($totalSpent, 0, ',', '.');
        $totalBudgetedFormatted = 'Rp' . number_format($totalBudgeted, 0, ',', '.');

        return "📊 *Ringkasan Budget Bulan Ini*\n\n" .
               implode("\n\n", $sections) . "\n" .
               "-----------------------------------\n" .
               "Total Terpakai: {$totalSpentFormatted} / {$totalBudgetedFormatted} ({$totalPercent}%)";
    }
}
