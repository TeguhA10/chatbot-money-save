<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * HealthController
 *
 * Exposes Docker / Kubernetes liveness and readiness probe endpoints.
 */
class HealthController extends Controller
{
    /**
     * GET /api/health/live
     */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * GET /api/health/ready
     */
    public function ready(): JsonResponse
    {
        return response()->json(['status' => 'ready']);
    }
}
