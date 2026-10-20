<?php

namespace App\Http\Controllers\Api;

use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PlanController extends Controller
{
    /**
     * Get all active subscription plans.
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->get()
            ->map(function ($plan) {
                return [
                    'id'                  => $plan->id,
                    'code'                => $plan->code,
                    'name'                => $plan->name,
                    'tagline'             => $plan->tagline,
                    'duration_days'       => $plan->duration_days,
                    'price'               => $plan->price,
                    'formatted_price'     => $plan->formatted_price,
                    'short_price'         => $plan->short_price,
                    'original_price'      => $plan->original_price,
                    'discount_percentage' => $plan->discount_percentage,
                    'badge'               => $plan->badge,
                    'features'            => $plan->features ?? [],
                    'is_popular'          => $plan->is_popular,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }
}
