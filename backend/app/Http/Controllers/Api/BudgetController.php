<?php

namespace App\Http\Controllers\Api;

use App\Models\Budget;
use App\Models\Category;
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
        $budgets = Budget::where('user_jid', $user->jid)->with('category')->get();

        $totalBudgeted = 0;
        $totalSpent = 0;
        $items = [];

        foreach ($budgets as $budget) {
            $consumption = $this->budgetService->getConsumption($budget, $now->year, $now->month);
            $totalBudgeted += $consumption['limit_amount'];
            $totalSpent += $consumption['spent_amount'];

            $cat = $budget->category;
            $items[] = [
                'id'               => $budget->id,
                'category_id'      => $budget->category_id,
                'category_name'    => $cat ? $cat->name : 'Unknown',
                'category_icon'    => $cat ? $cat->icon : '📌',
                'limit_amount'     => $consumption['limit_amount'],
                'spent_amount'     => $consumption['spent_amount'],
                'remaining_amount' => $consumption['remaining_amount'],
                'percentage'       => $consumption['percentage'],
                'is_exceeded'      => $consumption['is_exceeded'],
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'month'          => $now->format('Y-m'),
                'total_budgeted' => $totalBudgeted,
                'total_spent'    => $totalSpent,
                'budgets'        => $items,
            ],
        ]);
    }

    /**
     * POST /api/v1/budgets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id'  => 'required|uuid|exists:categories,id',
            'limit_amount' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $budget = $this->budgetService->setBudget(
            $user,
            $validated['category_id'],
            $validated['limit_amount']
        );

        $consumption = $this->budgetService->getConsumption($budget);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $budget->id,
                'category_id'      => $budget->category_id,
                'limit_amount'     => $budget->limit_amount,
                'spent_amount'     => $consumption['spent_amount'],
                'remaining_amount' => $consumption['remaining_amount'],
                'percentage'       => $consumption['percentage'],
            ],
        ], 201);
    }
}
