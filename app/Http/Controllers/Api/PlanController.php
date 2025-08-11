<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * Get all active plans.
     */
    public function index(Request $request): JsonResponse
    {
        $plans = Plan::active()
            ->orderBy('price')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'formatted_price' => $plan->formatted_price,
                    'billing_period' => $plan->billing_period,
                    'billing_period_display' => $plan->billing_period_display,
                    'features' => $plan->features ?? [],
                    'limits' => $plan->limits ?? [],
                    'is_free' => $plan->isFree(),
                    'trial_days' => $plan->trial_days,
                    'is_popular' => $plan->price > 0 && $plan->price < 50, // Mark mid-tier as popular
                ];
            });

        return response()->json([
            'plans' => $plans,
        ]);
    }

    /**
     * Get a specific plan.
     */
    public function show(Plan $plan): JsonResponse
    {
        if (!$plan->is_active) {
            return response()->json([
                'message' => 'Plan not found or inactive'
            ], 404);
        }

        return response()->json([
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price' => $plan->price,
                'formatted_price' => $plan->formatted_price,
                'billing_period' => $plan->billing_period,
                'billing_period_display' => $plan->billing_period_display,
                'features' => $plan->features ?? [],
                'limits' => $plan->limits ?? [],
                'is_free' => $plan->isFree(),
                'trial_days' => $plan->trial_days,
                'stripe_price_id' => $plan->stripe_price_id,
            ],
        ]);
    }

    /**
     * Compare plans for upgrade/downgrade decisions.
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'plan_ids' => 'required|array|min:2|max:4',
            'plan_ids.*' => 'exists:plans,id',
        ]);

        $plans = Plan::whereIn('id', $request->plan_ids)
            ->active()
            ->orderBy('price')
            ->get();

        if ($plans->count() !== count($request->plan_ids)) {
            return response()->json([
                'message' => 'One or more plans not found or inactive'
            ], 404);
        }

        // Get all unique features across plans
        $allFeatures = $plans->pluck('features')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        // Get all unique limits across plans
        $allLimits = $plans->pluck('limits')
            ->map(fn($limits) => array_keys($limits ?? []))
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        $comparison = [
            'plans' => $plans->map(function ($plan) use ($allFeatures, $allLimits) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'formatted_price' => $plan->formatted_price,
                    'billing_period' => $plan->billing_period,
                    'trial_days' => $plan->trial_days,
                    'features' => $allFeatures->mapWithKeys(function ($feature) use ($plan) {
                        return [$feature => $plan->hasFeature($feature)];
                    }),
                    'limits' => $allLimits->mapWithKeys(function ($limit) use ($plan) {
                        return [$limit => $plan->getLimit($limit)];
                    }),
                ];
            }),
            'feature_list' => $allFeatures,
            'limit_list' => $allLimits,
        ];

        return response()->json($comparison);
    }

    /**
     * Get upgrade recommendations for a user.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = auth()->user();
        $currentSubscription = $user->tenant?->subscription();
        
        if (!$currentSubscription) {
            // No subscription, recommend starter plans
            $recommendations = Plan::active()
                ->where('price', '>', 0)
                ->orderBy('price')
                ->limit(3)
                ->get();
        } else {
            // Get plans that are upgrades from current plan
            $currentPlan = $currentSubscription->plan;
            $recommendations = Plan::active()
                ->where('price', '>', $currentPlan->price)
                ->orderBy('price')
                ->limit(3)
                ->get();
        }

        return response()->json([
            'current_plan' => $currentSubscription?->plan,
            'recommendations' => $recommendations->map(function ($plan) use ($currentSubscription) {
                $proration = null;
                if ($currentSubscription) {
                    $proration = $currentSubscription->calculateProration($plan);
                }

                return [
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'price' => $plan->price,
                        'formatted_price' => $plan->formatted_price,
                        'billing_period' => $plan->billing_period,
                        'features' => $plan->features,
                        'limits' => $plan->limits,
                    ],
                    'proration' => $proration,
                    'upgrade_reasons' => $this->getUpgradeReasons($currentSubscription?->plan, $plan),
                ];
            }),
        ]);
    }

    /**
     * Get reasons to upgrade from one plan to another.
     */
    protected function getUpgradeReasons(?Plan $currentPlan, Plan $newPlan): array
    {
        if (!$currentPlan) {
            return [
                'Start with our ' . $newPlan->name . ' plan',
                'Full access to premium features',
                $newPlan->trial_days ? $newPlan->trial_days . ' day free trial' : null,
            ];
        }

        $reasons = [];
        
        // Compare features
        $newFeatures = array_diff($newPlan->features ?? [], $currentPlan->features ?? []);
        if (!empty($newFeatures)) {
            $reasons[] = 'Access to ' . implode(', ', $newFeatures);
        }

        // Compare limits
        $currentLimits = $currentPlan->limits ?? [];
        $newLimits = $newPlan->limits ?? [];
        
        foreach ($newLimits as $key => $value) {
            $currentValue = $currentLimits[$key] ?? 0;
            if ($value > $currentValue) {
                $reasons[] = "Increase {$key} from {$currentValue} to {$value}";
            }
        }

        // Add generic benefits if no specific differences found
        if (empty($reasons)) {
            $reasons[] = 'Enhanced features and capabilities';
            $reasons[] = 'Priority support';
        }

        return array_filter($reasons);
    }
}