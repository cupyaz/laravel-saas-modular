<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class WebhookController extends CashierController
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle customer subscription created.
     */
    public function handleCustomerSubscriptionCreated(array $payload): Response
    {
        try {
            $stripeSubscription = $payload['data']['object'];
            
            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription['id'])->first();
            
            if ($subscription) {
                $subscription->update([
                    'status' => $stripeSubscription['status'],
                    'current_period_start' => now()->createFromTimestamp($stripeSubscription['current_period_start']),
                    'current_period_end' => now()->createFromTimestamp($stripeSubscription['current_period_end']),
                ]);

                // Trigger webhook events
                $this->webhookService->dispatch('subscription.created', [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'status' => $subscription->status,
                ], null, $subscription->tenant_id);

                Log::info('Subscription created via webhook', [
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $stripeSubscription['id'],
                ]);
            }

            return $this->successMethod();

        } catch (\Exception $e) {
            Log::error('Failed to handle subscription created webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return $this->successMethod(); // Return success to avoid retries
        }
    }

    /**
     * Handle customer subscription updated.
     */
    public function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        try {
            $stripeSubscription = $payload['data']['object'];
            
            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription['id'])->first();
            
            if ($subscription) {
                $oldStatus = $subscription->status;
                
                $subscription->update([
                    'status' => $stripeSubscription['status'],
                    'current_period_start' => now()->createFromTimestamp($stripeSubscription['current_period_start']),
                    'current_period_end' => now()->createFromTimestamp($stripeSubscription['current_period_end']),
                ]);

                // Handle status changes
                if ($oldStatus !== $stripeSubscription['status']) {
                    $this->handleStatusChange($subscription, $oldStatus, $stripeSubscription['status']);
                }

                // Trigger webhook events
                $this->webhookService->dispatch('subscription.updated', [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'old_status' => $oldStatus,
                    'new_status' => $subscription->status,
                ], null, $subscription->tenant_id);

                Log::info('Subscription updated via webhook', [
                    'subscription_id' => $subscription->id,
                    'old_status' => $oldStatus,
                    'new_status' => $subscription->status,
                ]);
            }

            return $this->successMethod();

        } catch (\Exception $e) {
            Log::error('Failed to handle subscription updated webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return $this->successMethod();
        }
    }

    /**
     * Handle customer subscription deleted.
     */
    public function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        try {
            $stripeSubscription = $payload['data']['object'];
            
            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription['id'])->first();
            
            if ($subscription) {
                $subscription->update([
                    'status' => 'canceled',
                    'ends_at' => now(),
                ]);

                // Transition to cancelled state
                if ($subscription->canTransitionTo(Subscription::STATE_CANCELLED)) {
                    $subscription->transitionTo(Subscription::STATE_CANCELLED, [
                        'reason' => 'stripe_cancellation',
                    ]);
                }

                // Trigger webhook events
                $this->webhookService->dispatch('subscription.cancelled', [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'cancelled_at' => now()->toISOString(),
                ], null, $subscription->tenant_id);

                Log::info('Subscription cancelled via webhook', [
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $stripeSubscription['id'],
                ]);
            }

            return $this->successMethod();

        } catch (\Exception $e) {
            Log::error('Failed to handle subscription deleted webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return $this->successMethod();
        }
    }

    /**
     * Handle invoice payment succeeded.
     */
    public function handleInvoicePaymentSucceeded(array $payload): Response
    {
        try {
            $invoice = $payload['data']['object'];
            
            if ($invoice['subscription']) {
                $subscription = Subscription::where('stripe_subscription_id', $invoice['subscription'])->first();
                
                if ($subscription) {
                    // Trigger webhook events
                    $this->webhookService->dispatch('payment.succeeded', [
                        'subscription_id' => $subscription->id,
                        'tenant_id' => $subscription->tenant_id,
                        'invoice_id' => $invoice['id'],
                        'amount_paid' => $invoice['amount_paid'] / 100, // Convert from cents
                    ], null, $subscription->tenant_id);

                    Log::info('Payment succeeded via webhook', [
                        'subscription_id' => $subscription->id,
                        'invoice_id' => $invoice['id'],
                        'amount' => $invoice['amount_paid'] / 100,
                    ]);
                }
            }

            return $this->successMethod();

        } catch (\Exception $e) {
            Log::error('Failed to handle payment succeeded webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return $this->successMethod();
        }
    }

    /**
     * Handle invoice payment failed.
     */
    public function handleInvoicePaymentFailed(array $payload): Response
    {
        try {
            $invoice = $payload['data']['object'];
            
            if ($invoice['subscription']) {
                $subscription = Subscription::where('stripe_subscription_id', $invoice['subscription'])->first();
                
                if ($subscription) {
                    // Transition to past due if possible
                    if ($subscription->canTransitionTo(Subscription::STATE_PAST_DUE)) {
                        $subscription->transitionTo(Subscription::STATE_PAST_DUE);
                    }

                    // Trigger webhook events
                    $this->webhookService->dispatch('payment.failed', [
                        'subscription_id' => $subscription->id,
                        'tenant_id' => $subscription->tenant_id,
                        'invoice_id' => $invoice['id'],
                        'amount_due' => $invoice['amount_due'] / 100, // Convert from cents
                    ], null, $subscription->tenant_id);

                    Log::warning('Payment failed via webhook', [
                        'subscription_id' => $subscription->id,
                        'invoice_id' => $invoice['id'],
                        'amount_due' => $invoice['amount_due'] / 100,
                    ]);
                }
            }

            return $this->successMethod();

        } catch (\Exception $e) {
            Log::error('Failed to handle payment failed webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return $this->successMethod();
        }
    }

    /**
     * Handle status changes and trigger appropriate actions.
     */
    protected function handleStatusChange(Subscription $subscription, string $oldStatus, string $newStatus): void
    {
        // Map Stripe statuses to our internal states
        $stateMapping = [
            'active' => Subscription::STATE_ACTIVE,
            'trialing' => Subscription::STATE_TRIAL,
            'past_due' => Subscription::STATE_PAST_DUE,
            'canceled' => Subscription::STATE_CANCELLED,
            'unpaid' => Subscription::STATE_PAST_DUE,
        ];

        $newInternalStatus = $stateMapping[$newStatus] ?? null;

        if ($newInternalStatus && $subscription->canTransitionTo($newInternalStatus)) {
            $subscription->transitionTo($newInternalStatus);
        }
    }
}