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
        return response()->json([
            'success' => true,
            'data'    => [],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => ['code' => 'FEATURE_DISABLED', 'message' => 'Kategori sudah tidak digunakan lagi.'],
        ], 400);
    }
}
