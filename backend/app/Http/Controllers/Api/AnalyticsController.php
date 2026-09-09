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
     * Returns expense distribution per category for pie/donut charts.
     */
    public function categoryBreakdown(Request $request): JsonResponse
    {
        $user  = $request->user();
        $month = $request->get('month', now()->format('Y-m'));
        [$year, $monthNum] = explode('-', $month);

        $summary    = $this->financeService->getMonthlySummary($user, (int) $year, (int) $monthNum);
        $breakdown  = $summary['category_breakdown'];
        $totalExpense = $summary['total_expense'];

        // Add percentage to each category
        $result = array_map(function ($cat) use ($totalExpense) {
            $cat['percentage'] = $totalExpense > 0
                ? round(($cat['total_amount'] / $totalExpense) * 100, 1)
                : 0;
            return $cat;
        }, $breakdown);

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * GET /api/v1/analytics/monthly-trend
     * Returns income vs expense comparison for last 6 months (for bar/line charts).
     */
    public function monthlyTrend(Request $request): JsonResponse
    {
        $user   = $request->user();
        $result = [];

        for ($i = 5; $i >= 0; $i--) {
            $date    = now()->subMonths($i);
            $summary = $this->financeService->getMonthlySummary($user, $date->year, $date->month);

            $result[] = [
                'month'   => $date->format('Y-m'),
                'income'  => $summary['total_income'],
                'expense' => $summary['total_expense'],
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
}
