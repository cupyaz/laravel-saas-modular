<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\RetentionOffer;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionController extends Controller
{
    /**
     * Get user's current subscriptions.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'subscriptions' => [],
                'message' => 'No tenant found for user',
            ]);
        }

        $subscriptions = $tenant->subscriptions()
            ->with(['plan', 'retentionOffers' => function ($query) {
                $query->valid()->latest();
            }])
            ->latest()
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'internal_status' => $subscription->internal_status,
                    'status_display' => $subscription->status_display,
                    'status_color' => $subscription->status_color,
                    'plan' => [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'price' => $subscription->plan->price,
                        'formatted_price' => $subscription->plan->formatted_price,
                        'billing_period' => $subscription->plan->billing_period,
                        'billing_period_display' => $subscription->plan->billing_period_display,
                        'features' => $subscription->plan->features,
                    ],
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'trial_ends_at' => $subscription->trial_ends_at,
                    'ends_at' => $subscription->ends_at,
                    'paused_at' => $subscription->paused_at,
                    'grace_period_ends_at' => $subscription->grace_period_ends_at,
                    'cancellation_reason' => $subscription->cancellation_reason,
                    'is_active' => $subscription->isActive(),
                    'is_cancelled' => $subscription->isCancelled(),
                    'is_paused' => $subscription->isPaused(),
                    'on_trial' => $subscription->onTrial(),
                    'in_grace_period' => $subscription->inGracePeriod(),
                    'remaining_trial_days' => $subscription->getRemainingTrialDays(),
                    'grace_period_days_remaining' => $subscription->getGracePeriodDaysRemaining(),
                    'next_billing_date' => $subscription->getNextBillingDate(),
                    'retention_offers' => $subscription->retentionOffers->map(function ($offer) {
                        return [
                            'id' => $offer->id,
                            'offer_type' => $offer->offer_type,
                            'formatted_discount' => $offer->formatted_discount,
                            'formatted_savings' => $offer->formatted_savings,
                            'urgency_level' => $offer->getUrgencyLevel(),
                            'time_remaining' => $offer->time_remaining,
                            'valid_until' => $offer->valid_until,
                        ];
                    }),
                ];
            });

        return response()->json([
            'subscriptions' => $subscriptions,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
        ]);
    }

    /**
     * Get details for a specific subscription.
     */
    public function show(Subscription $subscription): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->tenant || $subscription->tenant_id !== $user->tenant->id) {
            return response()->json([
                'message' => 'Subscription not found or access denied'
            ], 404);
        }

        $subscription->load(['plan', 'retentionOffers']);

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'internal_status' => $subscription->internal_status,
                'plan' => $subscription->plan,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'trial_ends_at' => $subscription->trial_ends_at,
                'ends_at' => $subscription->ends_at,
                'paused_at' => $subscription->paused_at,
                'grace_period_ends_at' => $subscription->grace_period_ends_at,
                'cancellation_reason' => $subscription->cancellation_reason,
                'cancellation_feedback' => $subscription->cancellation_feedback,
                'retention_offers' => $subscription->retentionOffers,
            ],
        ]);
    }

    /**
     * Upgrade or downgrade subscription plan.
     */
    public function changePlan(Request $request, Subscription $subscription): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'prorate' => 'boolean',
        ]);

        $user = Auth::user();
        
        if (!$user->tenant || $subscription->tenant_id !== $user->tenant->id) {
            return response()->json([
                'message' => 'Subscription not found or access denied'
            ], 404);
        }

        if (!$subscription->isActive()) {
            return response()->json([
                'message' => 'Cannot change plan for inactive subscription'
            ], 400);
        }

        $newPlan = Plan::findOrFail($request->plan_id);
        
        if ($subscription->plan_id === $newPlan->id) {
            return response()->json([
                'message' => 'Subscription is already on this plan'
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            // Calculate proration
            $proration = $subscription->calculateProration($newPlan);
            
            // Update Stripe subscription if exists
            if ($subscription->stripe_subscription_id && $user->hasStripeId()) {
                $stripeSubscription = $user->subscription('default');
                
                if ($stripeSubscription) {
                    // Change the subscription plan in Stripe
                    $stripeSubscription->swap($newPlan->stripe_price_id);
                }
            }

            // Update our subscription record
            $subscription->update([
                'plan_id' => $newPlan->id,
            ]);

            DB::commit();

            Log::info('Subscription plan changed', [
                'subscription_id' => $subscription->id,
                'old_plan_id' => $subscription->plan_id,
                'new_plan_id' => $newPlan->id,
                'proration' => $proration,
            ]);

            return response()->json([
                'message' => 'Subscription plan changed successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'plan' => $newPlan,
                    'proration' => $proration,
                ],
            ]);

        } catch (IncompletePayment $exception) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Payment confirmation required',
                'payment_intent' => [
                    'id' => $exception->payment->id,
                    'client_secret' => $exception->payment->client_secret,
                ],
                'requires_action' => true,
            ], 402);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Plan change failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
                'new_plan_id' => $newPlan->id,
            ]);

            return response()->json([
                'message' => 'Failed to change subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pause subscription.
     */
    public function pause(Subscription $subscription): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->tenant || $subscription->tenant_id !== $user->tenant->id) {
            return response()->json([
                'message' => 'Subscription not found or access denied'
            ], 404);
        }

        if (!$subscription->canTransitionTo(Subscription::STATE_PAUSED)) {
            return response()->json([
                'message' => 'Cannot pause subscription in current state'
            ], 400);
        }

        try {
            $subscription->pause();

            Log::info('Subscription paused', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
            ]);

            return response()->json([
                'message' => 'Subscription paused successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->internal_status,
                    'paused_at' => $subscription->paused_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to pause subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Failed to pause subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume paused subscription.
     */
    public function resume(Subscription $subscription): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->tenant || $subscription->tenant_id !== $user->tenant->id) {
            return response()->json([
                'message' => 'Subscription not found or access denied'
            ], 404);
        }

        if (!$subscription->isPaused()) {
            return response()->json([
                'message' => 'Subscription is not paused'
            ], 400);
        }

        try {
            $subscription->resume();

            Log::info('Subscription resumed', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
            ]);

            return response()->json([
                'message' => 'Subscription resumed successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->internal_status,
                    'paused_at' => null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to resume subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Failed to resume subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription with optional retention offer.
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
            'feedback' => 'nullable|array',
            'feedback.*.category' => 'required|string',
            'feedback.*.comment' => 'nullable|string',
            'immediate' => 'boolean',
        ]);

        $user = Auth::user();
        
        if (!$user->tenant || $subscription->tenant_id !== $user->tenant->id) {
            return response()->json([
                'message' => 'Subscription not found or access denied'
            ], 404);
        }

        if ($subscription->isCancelled()) {
            return response()->json([
                'message' => 'Subscription is already cancelled'
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            // Check if we should show a retention offer
            $retentionOffer = null;
            if ($subscription->shouldShowRetentionOffer()) {
                $retentionOffer = $this->createRetentionOffer($subscription);
                $subscription->markRetentionOfferShown();
            }

            // Cancel the subscription
            $subscription->cancel($request->reason, $request->feedback);

            // Cancel in Stripe if immediate cancellation requested
            if ($request->boolean('immediate') && $subscription->stripe_subscription_id && $user->hasStripeId()) {
                $stripeSubscription = $user->subscription('default');
                if ($stripeSubscription) {
                    $stripeSubscription->cancelNow();
                }
            }

            DB::commit();

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'reason' => $request->reason,
                'immediate' => $request->boolean('immediate'),
                'retention_offer_created' => $retentionOffer !== null,
            ]);

            $response = [
                'message' => 'Subscription cancelled successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->internal_status,
                    'ends_at' => $subscription->ends_at,
                    'grace_period_ends_at' => $subscription->grace_period_ends_at,
                    'cancellation_reason' => $subscription->cancellation_reason,
                ],
            ];

            if ($retentionOffer) {
                $response['retention_offer'] = [
                    'id' => $retentionOffer->id,
                    'offer_type' => $retentionOffer->offer_type,
                    'formatted_discount' => $retentionOffer->formatted_discount,
                    'formatted_savings' => $retentionOffer->formatted_savings,
                    'valid_until' => $retentionOffer->valid_until,
                    'urgency_level' => $retentionOffer->getUrgencyLevel(),
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivate cancelled subscription.
     */
    public function reactivate(Subscription $subscription): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->tenant || $subscription->tenant_id !== $user->tenant->id) {
            return response()->json([
                'message' => 'Subscription not found or access denied'
            ], 404);
        }

        if (!$subscription->isCancelled() || !$subscription->inGracePeriod()) {
            return response()->json([
                'message' => 'Cannot reactivate subscription in current state'
            ], 400);
        }

        try {
            // Reactivate in Stripe if exists
            if ($subscription->stripe_subscription_id && $user->hasStripeId()) {
                $stripeSubscription = $user->subscription('default');
                if ($stripeSubscription) {
                    $stripeSubscription->resume();
                }
            }

            $subscription->reactivate();

            Log::info('Subscription reactivated', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
            ]);

            return response()->json([
                'message' => 'Subscription reactivated successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->internal_status,
                    'ends_at' => null,
                    'grace_period_ends_at' => null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reactivate subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Failed to reactivate subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept a retention offer.
     */
    public function acceptRetentionOffer(Request $request, Subscription $subscription, RetentionOffer $offer): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->tenant || $subscription->tenant_id !== $user->tenant->id || $offer->subscription_id !== $subscription->id) {
            return response()->json([
                'message' => 'Offer not found or access denied'
            ], 404);
        }

        if (!$offer->isValid()) {
            return response()->json([
                'message' => 'This offer has expired or is no longer valid'
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            // Accept the offer
            $offer->accept();

            // Apply the offer based on type
            switch ($offer->offer_type) {
                case RetentionOffer::TYPE_DISCOUNT:
                    // Apply discount to next billing cycle
                    // This would typically involve Stripe coupon creation
                    break;
                    
                case RetentionOffer::TYPE_FREE_MONTHS:
                    // Extend current period by free months
                    if ($subscription->current_period_end) {
                        $subscription->update([
                            'current_period_end' => $subscription->current_period_end->addMonths($offer->free_months)
                        ]);
                    }
                    break;
                    
                case RetentionOffer::TYPE_PLAN_DOWNGRADE:
                    // Change to downgrade plan
                    $subscription->update(['plan_id' => $offer->downgrade_plan_id]);
                    break;
            }

            // Reactivate the subscription
            $subscription->reactivate();

            DB::commit();

            Log::info('Retention offer accepted', [
                'subscription_id' => $subscription->id,
                'offer_id' => $offer->id,
                'offer_type' => $offer->offer_type,
            ]);

            return response()->json([
                'message' => 'Retention offer accepted successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->internal_status,
                ],
                'offer' => [
                    'id' => $offer->id,
                    'accepted_at' => $offer->accepted_at,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to accept retention offer', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
                'offer_id' => $offer->id,
            ]);

            return response()->json([
                'message' => 'Failed to accept retention offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a retention offer for a cancelling subscription.
     */
    protected function createRetentionOffer(Subscription $subscription): ?RetentionOffer
    {
        // Determine offer type based on subscription plan and history
        $plan = $subscription->plan;
        
        if ($plan->price > 50) {
            // High-value plans get percentage discount
            return RetentionOffer::createPercentageDiscount(
                $subscription,
                25,
                72,
                "Stay with us! Get 25% off your next 3 months."
            );
        } elseif ($plan->price > 20) {
            // Mid-tier plans get free months
            return RetentionOffer::createFreeMonths(
                $subscription,
                1,
                72,
                "Don't go yet! Enjoy 1 month free if you stay."
            );
        } else {
            // Lower-tier plans get fixed discount
            return RetentionOffer::createFixedDiscount(
                $subscription,
                5.00,
                72,
                "We'll miss you! Here's $5 off your next payment."
            );
        }
    }
}