<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Basic', 'Premium', 'Enterprise']),
            'slug' => $this->faker->slug(2),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 9.99, 99.99),
            'billing_period' => $this->faker->randomElement(['monthly', 'yearly']),
            'features' => [
                'basic_feature',
                'advanced_feature',
                'premium_support'
            ],
            'limits' => [
                'users' => $this->faker->numberBetween(1, 100),
                'projects' => $this->faker->numberBetween(1, 50),
                'storage_gb' => $this->faker->numberBetween(1, 1000),
            ],
            'is_active' => true,
            'stripe_price_id' => 'price_' . $this->faker->randomNumber(8),
            'trial_days' => $this->faker->randomElement([0, 7, 14, 30]),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0.00,
            'name' => 'Free',
            'features' => ['basic_feature'],
            'limits' => [
                'users' => 1,
                'projects' => 3,
                'storage_gb' => 1,
            ],
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 29.99,
            'name' => 'Premium',
            'features' => [
                'basic_feature',
                'advanced_feature',
                'premium_support',
                'api_access'
            ],
            'limits' => [
                'users' => 10,
                'projects' => 50,
                'storage_gb' => 100,
            ],
        ]);
    }
}