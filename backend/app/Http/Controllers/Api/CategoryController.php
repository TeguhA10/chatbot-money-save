<?php

namespace App\Http\Controllers\Api;

use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CategoryController
 *
 * Manages system-wide default categories and user custom categories.
 */
class CategoryController extends Controller
{
    public function __construct(private readonly FinanceService $financeService) {}

    /**
     * GET /api/v1/categories
     * Returns all categories visible to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = $this->financeService->getCategoriesForUser($request->user());

        return response()->json([
            'success' => true,
            'data'    => $categories->map(fn($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'type'       => $c->type,
                'icon'       => $c->icon,
                'is_default' => $c->is_default,
            ]),
        ]);
    }

    /**
     * POST /api/v1/categories
     * Creates a new custom category for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'type' => 'required|in:EXPENSE,INCOME',
            'icon' => 'nullable|string|max:10',
        ]);

        try {
            $category = $this->financeService->addCustomCategory(
                $request->user(),
                $validated['name'],
                $validated['type'],
                $validated['icon'] ?? '📌'
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'         => $category->id,
                    'name'       => $category->name,
                    'type'       => $category->type,
                    'icon'       => $category->icon,
                    'is_default' => false,
                ],
            ], 201);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DUPLICATE_CATEGORY', 'message' => $e->getMessage()],
            ], 422);
        }
    }
}
