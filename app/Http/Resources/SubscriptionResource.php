<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SubscriptionResource extends BaseApiResource
{
    /**
     * Transform the subscription data.
     */
    protected function transformData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'stripe_subscription_id' => $this->resource->stripe_subscription_id,
            'status' => $this->resource->status,
            'internal_status' => $this->resource->internal_status,
            'status_display' => $this->resource->status_display,
            'status_color' => $this->resource->status_color,
            'quantity' => $this->resource->quantity,
            
            // Status checks
            'is_active' => $this->formatBoolean($this->resource->isActive(), 'Active', 'Inactive'),
            'is_cancelled' => $this->formatBoolean($this->resource->isCancelled(), 'Cancelled', 'Active'),
            'is_paused' => $this->formatBoolean($this->resource->isPaused(), 'Paused', 'Running'),
            'on_trial' => $this->formatBoolean($this->resource->onTrial(), 'On Trial', 'Not on Trial'),
            'has_expired' => $this->formatBoolean($this->resource->hasExpired(), 'Expired', 'Current'),
            'in_grace_period' => $this->formatBoolean($this->resource->inGracePeriod(), 'Grace Period', 'Normal'),
            'is_past_due' => $this->formatBoolean($this->resource->isPastDue(), 'Past Due', 'Current'),
            
            // Dates
            'current_period_start' => $this->formatDate($this->resource->current_period_start),
            'current_period_end' => $this->formatDate($this->resource->current_period_end),
            'trial_ends_at' => $this->formatDate($this->resource->trial_ends_at),
            'ends_at' => $this->formatDate($this->resource->ends_at),
            'paused_at' => $this->formatDate($this->resource->paused_at),
            'grace_period_ends_at' => $this->formatDate($this->resource->grace_period_ends_at),
            'created_at' => $this->formatDate($this->resource->created_at),
            'updated_at' => $this->formatDate($this->resource->updated_at),
            
            // Billing information
            'next_billing_date' => $this->formatDate($this->resource->getNextBillingDate()),
            'formatted_price' => $this->resource->formatted_price,
            
            // Trial information
            'remaining_trial_days' => $this->when($this->resource->onTrial(), 
                $this->resource->getRemainingTrialDays()
            ),
            
            // Grace period information
            'grace_period_days_remaining' => $this->when($this->resource->inGracePeriod(),
                $this->resource->getGracePeriodDaysRemaining()
            ),
            
            // Cancellation information
            'cancellation_reason' => $this->resource->cancellation_reason,
            'cancellation_feedback' => $this->resource->cancellation_feedback,
            
            // Retention offer information
            'should_show_retention_offer' => $this->resource->shouldShowRetentionOffer(),
            'retention_offer_shown' => $this->formatBoolean(
                $this->resource->retention_offer_shown,
                'Shown',
                'Not Shown'
            ),
            'retention_offer_shown_at' => $this->formatDate($this->resource->retention_offer_shown_at),
            
            // Relationships
            'tenant' => $this->whenLoaded('tenant', function () {
                return new TenantResource($this->resource->tenant);
            }),
            
            'plan' => $this->whenLoaded('plan', function () {
                return new PlanResource($this->resource->plan);
            }),
            
            'items' => $this->whenLoaded('items', function () {
                return SubscriptionItemResource::collection($this->resource->items);
            }),
            
            'retention_offers' => $this->whenLoaded('retentionOffers', function () {
                return RetentionOfferResource::collection($this->resource->retentionOffers);
            }),
            
            // Metadata
            'metadata' => $this->resource->metadata ?? [],
        ];
    }

    /**
     * Get the links for the subscription resource.
     */
    protected function links(Request $request): array
    {
        $links = parent::links($request);
        
        // Add action links based on subscription state
        if ($this->resource->isActive()) {
            if (!$this->resource->isPaused()) {
                $links['pause'] = route('api.subscriptions.pause', $this->resource->id);
            } else {
                $links['resume'] = route('api.subscriptions.resume', $this->resource->id);
            }
            
            $links['cancel'] = route('api.subscriptions.cancel', $this->resource->id);
            $links['change_plan'] = route('api.subscriptions.change-plan', $this->resource->id);
        }
        
        if ($this->resource->isCancelled() && $this->resource->inGracePeriod()) {
            $links['reactivate'] = route('api.subscriptions.reactivate', $this->resource->id);
        }
        
        $links['tenant'] = route('api.tenant.show');
        $links['plan'] = route('api.plans.show', $this->resource->plan_id);
        
        return $links;
    }

    /**
     * Get subscription-specific meta information.
     */
    protected function meta(Request $request): array
    {
        $meta = parent::meta($request);
        
        // Add transition capabilities
        $availableTransitions = [];
        foreach (['active', 'paused', 'cancelled', 'expired'] as $state) {
            if ($this->resource->canTransitionTo($state)) {
                $availableTransitions[] = $state;
            }
        }
        
        $meta['available_transitions'] = $availableTransitions;
        $meta['subscription_phase'] = $this->getSubscriptionPhase();
        
        return $meta;
    }

    /**
     * Determine the current subscription phase.
     */
    private function getSubscriptionPhase(): string
    {
        if ($this->resource->onTrial()) {
            return 'trial';
        } elseif ($this->resource->inGracePeriod()) {
            return 'grace_period';
        } elseif ($this->resource->isPaused()) {
            return 'paused';
        } elseif ($this->resource->isActive()) {
            return 'active';
        } elseif ($this->resource->hasExpired()) {
            return 'expired';
        }
        
        return 'unknown';
    }
}