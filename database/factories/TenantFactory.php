<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(2),
            'domain' => $this->faker->unique()->domainName(),
            'database' => null,
            'config' => [
                'timezone' => 'UTC',
                'locale' => 'en',
                'features' => ['basic'],
            ],
            'billing_address' => [
                'country' => $this->faker->countryCode(),
                'state' => $this->faker->stateAbbr(),
                'city' => $this->faker->city(),
                'postal_code' => $this->faker->postcode(),
                'line1' => $this->faker->streetAddress(),
            ],
            'tax_id' => $this->faker->optional()->numerify('##########'),
            'is_active' => true,
            'trial_ends_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function onTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }

    public function trialExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}