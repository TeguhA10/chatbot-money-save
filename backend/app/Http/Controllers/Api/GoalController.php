<?php

namespace App\Http\Controllers\Api;

use App\Models\FinancialGoal;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GoalController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeService
    ) {}

    /**
     * GET /api/v1/goals
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $goals = FinancialGoal::where('user_jid', $user->jid)->orderBy('created_at')->get();

        $items = [];
        foreach ($goals as $goal) {
            $items[] = [
                'id'               => $goal->id,
                'name'             => $goal->name,
                'target_amount'    => $goal->target_amount,
                'current_amount'   => $goal->current_amount,
                'progress_percent' => $goal->progress_percent,
                'target_date'      => $goal->target_date?->toDateString(),
                'status'           => $goal->status,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'goals' => $items,
            ],
        ]);
    }

    /**
     * POST /api/v1/goals
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'target_amount' => 'required|integer|min:1',
            'target_date'   => 'nullable|date',
        ]);

        $user = $request->user();
        $goal = $this->financeService->createGoal(
            $user,
            $validated['name'],
            $validated['target_amount'],
            $validated['target_date'] ?? null
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $goal->id,
                'name'             => $goal->name,
                'target_amount'    => $goal->target_amount,
                'current_amount'   => $goal->current_amount,
                'progress_percent' => $goal->progress_percent,
                'target_date'      => $goal->target_date?->toDateString(),
                'status'           => $goal->status,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/goals/{id}/contribute
     */
    public function contribute(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount'    => 'required|integer|min:1',
            'wallet_id' => 'nullable|uuid|exists:wallets,id',
        ]);

        $user = $request->user();
        $goal = $this->financeService->findGoalByNameOrId($user, $id);

        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Target finansial tidak ditemukan.',
            ], 404);
        }

        try {
            $result = $this->financeService->contributeToGoal(
                $user,
                $goal,
                $validated['amount'],
                $validated['wallet_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'               => $goal->id,
                    'name'             => $goal->name,
                    'target_amount'    => $goal->target_amount,
                    'current_amount'   => $goal->current_amount,
                    'progress_percent' => $goal->progress_percent,
                    'status'           => $goal->status,
                ],
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
