<?php

namespace App\Http\Controllers\Api;

use App\Models\Budget;
use App\Services\BudgetService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgetService
    ) {}

    /**
     * GET /api/v1/budgets
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();
        $budget = Budget::where('user_jid', $user->jid)->first();

        if (!$budget) {
            $totalSpent = $this->budgetService->getMonthlyExpenseTotal($user->jid, $now->year, $now->month);
            return response()->json([
                'success' => true,
                'data'    => [
                    'month'          => $now->format('Y-m'),
                    'total_budgeted' => 0,
                    'total_spent'    => $totalSpent,
                    'budget'         => null,
                ],
            ]);
        }

        $consumption = $this->budgetService->getConsumption($budget, $now->year, $now->month);

        return response()->json([
            'success' => true,
            'data'    => [
                'month'          => $now->format('Y-m'),
                'total_budgeted' => $consumption['limit_amount'],
                'total_spent'    => $consumption['spent_amount'],
                'budget'         => [
                    'id'               => $budget->id,
                    'limit_amount'     => $consumption['limit_amount'],
                    'spent_amount'     => $consumption['spent_amount'],
                    'remaining_amount' => $consumption['remaining_amount'],
                    'percentage'       => $consumption['percentage'],
                    'is_exceeded'      => $consumption['is_exceeded'],
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/budgets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit_amount' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $budget = $this->budgetService->setBudget(
            $user,
            $validated['limit_amount']
        );

        $consumption = $this->budgetService->getConsumption($budget);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $budget->id,
                'limit_amount'     => $budget->limit_amount,
                'spent_amount'     => $consumption['spent_amount'],
                'remaining_amount' => $consumption['remaining_amount'],
                'percentage'       => $consumption['percentage'],
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/budgets
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->budgetService->deleteBudget($user);

        return response()->json([
            'success' => true,
            'message' => 'Plafon anggaran bulanan berhasil dihapus.',
        ]);
    }
}
