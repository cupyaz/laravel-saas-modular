<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PlanResource extends BaseApiResource
{
    /**
     * Transform the plan data.
     */
    protected function transformData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'price' => $this->formatCurrency($this->resource->price),
            'billing_period' => $this->resource->billing_period,
            'billing_period_display' => $this->resource->billing_period_display,
            'is_active' => $this->formatBoolean($this->resource->is_active, 'Available', 'Unavailable'),
            'is_free' => $this->formatBoolean($this->resource->isFree(), 'Free Plan', 'Paid Plan'),
            'trial_days' => $this->resource->trial_days,
            'stripe_price_id' => $this->resource->stripe_price_id,
            'created_at' => $this->formatDate($this->resource->created_at),
            'updated_at' => $this->formatDate($this->resource->updated_at),
            
            // Legacy features and limits
            'features' => $this->resource->features ?? [],
            'limits' => $this->resource->limits ?? [],
            
            // Enhanced feature relationships
            'plan_features' => $this->whenLoaded('features', function () {
                return $this->resource->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'slug' => $feature->slug,
                        'category' => $feature->category,
                        'is_included' => $feature->pivot->is_included,
                        'limit' => $feature->pivot->limit,
                        'is_unlimited' => $feature->pivot->isUnlimited(),
                        'effective_limit' => $feature->pivot->getEffectiveLimit(),
                    ];
                });
            }),
            
            // Subscription count
            'subscriptions_count' => $this->whenLoaded('subscriptions', function () {
                return $this->resource->subscriptions->count();
            }),
        ];
    }

    /**
     * Get the links for the plan resource.
     */
    protected function links(Request $request): array
    {
        return array_merge(parent::links($request), [
            'subscribe' => $this->when(!$this->resource->isFree(), 
                route('api.subscriptions.create', ['plan' => $this->resource->id])
            ),
            'features' => route('api.plans.features', $this->resource->id),
            'compare' => route('api.plans.compare', ['plans' => $this->resource->id]),
        ]);
    }

    /**
     * Get plan-specific meta information.
     */
    protected function meta(Request $request): array
    {
        return array_merge(parent::meta($request), [
            'plan_type' => $this->resource->isFree() ? 'free' : 'paid',
            'billing_cycle' => $this->resource->billing_period,
            'currency' => 'USD', // Could be made configurable
        ]);
    }
}