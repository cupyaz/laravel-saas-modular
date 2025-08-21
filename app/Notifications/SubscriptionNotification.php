<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\NotificationTemplate;
use App\Services\NotificationService;

class SubscriptionNotification
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send subscription created notification.
     */
    public function subscriptionCreated(Subscription $subscription): void
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        $variables = [
            'user_name' => $user->name,
            'plan_name' => $plan->name,
            'amount' => $subscription->formatted_price,
            'billing_cycle' => $subscription->billing_cycle,
            'next_billing_date' => $subscription->next_billing_date?->format('F j, Y'),
            'action_url' => url('/subscription/dashboard'),
            'action_text' => 'View Subscription',
        ];

        $this->notificationService->send(
            $user,
            'subscription_created',
            $variables
        );
    }

    /**
     * Send subscription cancelled notification.
     */
    public function subscriptionCancelled(Subscription $subscription, string $reason = null): void
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        $variables = [
            'user_name' => $user->name,
            'plan_name' => $plan->name,
            'cancellation_date' => $subscription->cancelled_at?->format('F j, Y'),
            'ends_at' => $subscription->ends_at?->format('F j, Y'),
            'reason' => $reason,
            'action_url' => url('/subscription/reactivate'),
            'action_text' => 'Reactivate Subscription',
        ];

        $this->notificationService->send(
            $user,
            'subscription_cancelled',
            $variables
        );
    }

    /**
     * Send subscription expired notification.
     */
    public function subscriptionExpired(Subscription $subscription): void
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        $variables = [
            'user_name' => $user->name,
            'plan_name' => $plan->name,
            'expired_date' => $subscription->ends_at?->format('F j, Y'),
            'action_url' => url('/subscription/renew'),
            'action_text' => 'Renew Subscription',
        ];

        $this->notificationService->send(
            $user,
            'subscription_expired',
            $variables
        );
    }

    /**
     * Send subscription renewal reminder.
     */
    public function subscriptionRenewalReminder(Subscription $subscription, int $daysUntilRenewal): void
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        $variables = [
            'user_name' => $user->name,
            'plan_name' => $plan->name,
            'renewal_date' => $subscription->next_billing_date?->format('F j, Y'),
            'days_until_renewal' => $daysUntilRenewal,
            'amount' => $subscription->formatted_price,
            'action_url' => url('/subscription/dashboard'),
            'action_text' => 'Manage Subscription',
        ];

        $this->notificationService->send(
            $user,
            'subscription_renewal_reminder',
            $variables
        );
    }

    /**
     * Send subscription plan changed notification.
     */
    public function subscriptionPlanChanged(Subscription $subscription, string $oldPlanName): void
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        $variables = [
            'user_name' => $user->name,
            'old_plan_name' => $oldPlanName,
            'new_plan_name' => $plan->name,
            'new_amount' => $subscription->formatted_price,
            'effective_date' => now()->format('F j, Y'),
            'action_url' => url('/subscription/dashboard'),
            'action_text' => 'View Changes',
        ];

        $this->notificationService->send(
            $user,
            'subscription_plan_changed',
            $variables
        );
    }

    /**
     * Send subscription payment failed notification.
     */
    public function subscriptionPaymentFailed(Subscription $subscription, string $failureReason): void
    {
        $user = $subscription->user;

        $variables = [
            'user_name' => $user->name,
            'amount' => $subscription->formatted_price,
            'failure_reason' => $failureReason,
            'retry_date' => now()->addDays(3)->format('F j, Y'),
            'action_url' => url('/subscription/payment-method'),
            'action_text' => 'Update Payment Method',
        ];

        $this->notificationService->send(
            $user,
            'payment_failed',
            $variables,
            [NotificationTemplate::CHANNEL_DATABASE, NotificationTemplate::CHANNEL_EMAIL]
        );
    }

    /**
     * Send trial expiring notification.
     */
    public function trialExpiring(Subscription $subscription, int $daysLeft): void
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        $variables = [
            'user_name' => $user->name,
            'plan_name' => $plan->name,
            'days_left' => $daysLeft,
            'trial_ends_at' => $subscription->trial_ends_at?->format('F j, Y'),
            'action_url' => url('/subscription/upgrade'),
            'action_text' => 'Upgrade Now',
        ];

        $this->notificationService->send(
            $user,
            'trial_expiring',
            $variables
        );
    }
}