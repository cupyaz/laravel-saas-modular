<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $currentPeriodStart = $this->faker->dateTimeBetween('-1 month', 'now');
        $currentPeriodEnd = $this->faker->dateTimeBetween($currentPeriodStart, '+1 month');

        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'stripe_subscription_id' => 'sub_' . $this->faker->randomNumber(8),
            'status' => $this->faker->randomElement(['active', 'trialing', 'past_due', 'canceled']),
            'internal_status' => Subscription::STATE_ACTIVE,
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'trial_ends_at' => $this->faker->optional(0.3)->dateTimeBetween('now', '+14 days'),
            'ends_at' => null,
            'paused_at' => null,
            'grace_period_ends_at' => null,
            'cancellation_reason' => null,
            'cancellation_feedback' => null,
            'retention_offer_shown' => false,
            'retention_offer_shown_at' => null,
            'metadata' => [
                'created_via' => 'api',
                'source' => 'direct',
            ],
            'quantity' => 1,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'internal_status' => Subscription::STATE_ACTIVE,
            'ends_at' => null,
            'paused_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'internal_status' => Subscription::STATE_CANCELLED,
            'ends_at' => $this->faker->dateTimeBetween('now', '+30 days'),
            'grace_period_ends_at' => $this->faker->dateTimeBetween('now', '+30 days'),
            'cancellation_reason' => $this->faker->randomElement([
                'too_expensive',
                'not_using',
                'missing_features',
                'found_alternative'
            ]),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
            'internal_status' => Subscription::STATE_PAUSED,
            'paused_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function onTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'internal_status' => Subscription::STATE_TRIAL,
            'trial_ends_at' => $this->faker->dateTimeBetween('now', '+14 days'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'internal_status' => Subscription::STATE_EXPIRED,
            'ends_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'past_due',
            'internal_status' => Subscription::STATE_PAST_DUE,
        ]);
    }

    public function inGracePeriod(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'internal_status' => Subscription::STATE_GRACE_PERIOD,
            'grace_period_ends_at' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }
}