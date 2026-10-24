<?php

namespace App\Http\Controllers\Api;

use App\Models\Transaction;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * AnalyticsController
 *
 * Provides aggregated financial data for charts and dashboards in Vue 3 and Expo clients.
 * All data is strictly scoped to the authenticated user (Constitution Principle I).
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly FinanceService $financeService) {}

    /**
     * GET /api/v1/analytics/summary?month=YYYY-MM
     * Returns KPI cards: total income, expense, net savings, balance, and delta.
     */
    public function summary(Request $request): JsonResponse
    {
        $user  = $request->user();
        $month = $request->get('month', now()->format('Y-m'));

        [$year, $monthNum] = explode('-', $month);

        $current  = $this->financeService->getMonthlySummary($user, (int) $year, (int) $monthNum);

        // Previous month for delta calculation
        $prevDate  = now()->setYear((int) $year)->setMonth((int) $monthNum)->subMonth();
        $previous  = $this->financeService->getMonthlySummary($user, $prevDate->year, $prevDate->month);

        $expenseDelta = $previous['total_expense'] > 0
            ? round((($current['total_expense'] - $previous['total_expense']) / $previous['total_expense']) * 100, 1)
            : 0;

        $incomeDelta = $previous['total_income'] > 0
            ? round((($current['total_income'] - $previous['total_income']) / $previous['total_income']) * 100, 1)
            : 0;

        return response()->json([
            'success' => true,
            'data'    => [
                'period'          => $month,
                'total_income'    => $current['total_income'],
                'total_expense'   => $current['total_expense'],
                'net_savings'     => $current['net_savings'],
                'current_balance' => $current['current_balance'],
                'delta_vs_last_month' => [
                    'expense_percentage' => $expenseDelta,
                    'income_percentage'  => $incomeDelta,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/analytics/category-breakdown?month=YYYY-MM
     * Deprecated: categories have been removed. Returns empty data.
     */
    public function categoryBreakdown(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => []]);
    }

    /**
     * GET /api/v1/analytics/dashboard-feed?month=YYYY-MM
     * High-performance consolidated round-trip feed for web app dashboard.
     * Prevents 9 separate parallel requests by packing all necessary widgets into a single payload.
     */
    public function dashboardFeed(Request $request): JsonResponse
    {
        $user  = $request->user();
        $month = $request->get('month', now()->format('Y-m'));
        [$year, $monthNum] = explode('-', $month);

        $cacheKey = "dashboard_feed:{$user->jid}:{$month}";
        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($user, $year, $monthNum, $month) {
            // 1. Current & Previous Summary
            $current  = $this->financeService->getMonthlySummary($user, (int) $year, (int) $monthNum);
            $prevDate = now()->setYear((int) $year)->setMonth((int) $monthNum)->subMonth();
            $previous = $this->financeService->getMonthlySummary($user, $prevDate->year, $prevDate->month);

            $expenseDelta = $previous['total_expense'] > 0
                ? round((($current['total_expense'] - $previous['total_expense']) / $previous['total_expense']) * 100, 1)
                : 0;

            $incomeDelta = $previous['total_income'] > 0
                ? round((($current['total_income'] - $previous['total_income']) / $previous['total_income']) * 100, 1)
                : 0;

            $summary = [
                'period'              => $month,
                'total_income'        => $current['total_income'],
                'total_expense'       => $current['total_expense'],
                'net_savings'         => $current['net_savings'],
                'current_balance'     => $current['current_balance'],
                'delta_vs_last_month' => [
                    'expense_percentage' => $expenseDelta,
                    'income_percentage'  => $incomeDelta,
                ],
            ];

            // 2. Category Breakdown (empty)
            $breakdown = [];

            // 3. Monthly Trend (6 months)
            $trend = $this->getMonthlyTrendData($user);

            // 4. Wallets
            $wallets = \App\Models\Wallet::where('user_jid', $user->jid)->orderBy('created_at')->get();
            if ($wallets->isEmpty()) {
                $this->financeService->getOrCreateDefaultWallet($user);
                $wallets = \App\Models\Wallet::where('user_jid', $user->jid)->get();
            }
            $walletItems = [];
            $totalWalletBalance = 0;
            foreach ($wallets as $w) {
                $bal = $w->balance;
                $totalWalletBalance += $bal;
                $walletItems[] = [
                    'id'         => $w->id,
                    'name'       => $w->name,
                    'type'       => $w->type,
                    'is_default' => $w->is_default,
                    'balance'    => $bal,
                ];
            }

            // 5. Monthly Budget
            $budgetService = app(\App\Services\BudgetService::class);
            $budget = \App\Models\Budget::where('user_jid', $user->jid)->first();
            $budgetItems = [];
            $totalBudgeted = 0;
            $totalSpent = 0;
            if ($budget) {
                $consumed = $budgetService->getConsumption($budget, (int) $year, (int) $monthNum);
                $totalBudgeted = $consumed['limit_amount'];
                $totalSpent = $consumed['spent_amount'];
                $budgetItems[] = [
                    'id'                      => $budget->id,
                    'limit_amount'            => $consumed['limit_amount'],
                    'spent_amount'            => $consumed['spent_amount'],
                    'remaining_amount'        => $consumed['remaining_amount'],
                    'percentage'              => $consumed['percentage'],
                    'is_warning'              => $consumed['is_warning'],
                    'is_exceeded'             => $consumed['is_exceeded'],
                    'progress_bar'            => $consumed['progress_bar'],
                    'alert_threshold_percent' => $budget->alert_threshold_percent,
                ];
            }

            // 6. Goals
            $goals = \App\Models\FinancialGoal::where('user_jid', $user->jid)->orderBy('created_at')->get();
            $goalItems = $goals->map(function ($g) {
                $pct = $g->target_amount > 0 ? round(($g->current_amount / $g->target_amount) * 100, 1) : 0;
                return [
                    'id'               => $g->id,
                    'name'             => $g->name,
                    'target_amount'    => $g->target_amount,
                    'current_amount'   => $g->current_amount,
                    'progress_percent' => min(100, $pct),
                    'target_date'      => $g->target_date?->toISOString(),
                ];
            })->toArray();

            // 7. Recent Transactions (limit 5)
            $recent = Transaction::where('user_jid', $user->jid)
                ->active()
                ->orderByDesc('transaction_date')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn($t) => [
                    'id'               => $t->id,
                    'type'             => $t->type,
                    'amount'           => $t->amount,
                    'description'      => $t->description,
                    'balance_after'    => $t->balance_after,
                    'transaction_date' => $t->transaction_date?->toISOString(),
                    'status'           => $t->status,
                ])
                ->toArray();

            return [
                'summary'            => $summary,
                'category_breakdown' => $breakdown,
                'monthly_trend'      => $trend,
                'wallets'            => [
                    'wallets'       => $walletItems,
                    'total_balance' => $totalWalletBalance,
                ],
                'budgets'            => [
                    'budgets'        => $budgetItems,
                    'total_budgeted' => $totalBudgeted,
                    'total_spent'    => $totalSpent,
                ],
                'goals'              => [
                    'goals' => $goalItems,
                ],
                'recent_transactions'=> $recent,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/v1/analytics/monthly-trend
     * Returns income vs expense comparison for last 6 months (for bar/line charts).
     */
    public function monthlyTrend(Request $request): JsonResponse
    {
        $user   = $request->user();
        $result = $this->getMonthlyTrendData($user);

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function getMonthlyTrendData($user): array
    {
        $startDate = now()->subMonths(5)->startOfMonth();
        $endDate   = now()->endOfMonth();

        $transactions = Transaction::where('user_jid', $user->jid)
            ->active()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();

        $byMonth = $transactions->groupBy(function ($t) {
            return $t->transaction_date ? $t->transaction_date->format('Y-m') : '';
        });

        $result = [];
        for ($i = 5; $i >= 0; $i--) {
            $date     = now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $group    = $byMonth->get($monthKey, collect());

            $result[] = [
                'month'   => $monthKey,
                'income'  => (int) $group->where('type', 'INCOME')->sum('amount'),
                'expense' => (int) $group->where('type', 'EXPENSE')->sum('amount'),
            ];
        }

        return $result;
    }
}
