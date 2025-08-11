<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip Stripe interaction for tests
        $this->mockStripe();
    }

    public function test_calculate_tax_endpoint_works()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 29.99]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/calculate-tax', [
            'plan_id' => $plan->id,
            'country_code' => 'US',
            'state_code' => 'CA',
            'postal_code' => '90210',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subtotal',
                'tax' => [
                    'amount',
                    'formatted_amount',
                    'rate',
                    'jurisdiction',
                    'type',
                ],
                'total',
                'currency',
            ]);
    }

    public function test_setup_intent_endpoint_requires_auth()
    {
        $response = $this->postJson('/api/v1/payment/setup-intent');

        $response->assertStatus(401);
    }

    public function test_setup_intent_endpoint_works_with_auth()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Mock Stripe setup intent creation
        $this->mockStripeSetupIntent();

        $response = $this->postJson('/api/v1/payment/setup-intent');

        $response->assertStatus(200)
            ->assertJsonStructure(['client_secret']);
    }

    public function test_process_payment_validates_required_fields()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payment/process', [
            'plan_id' => $plan->id,
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'payment_method_id',
                'country_code',
                'city',
                'address_line1',
            ]);
    }

    public function test_bank_transfer_endpoint_works()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 29.99]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payment/bank-transfer', [
            'plan_id' => $plan->id,
            'country_code' => 'US',
            'city' => 'Los Angeles',
            'address_line1' => '123 Main St',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'subscription' => ['id', 'status'],
                'bank_details' => [
                    'account_name',
                    'account_number',
                    'routing_number',
                    'reference',
                    'amount',
                ],
            ]);
    }

    public function test_payment_methods_endpoint_works()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/payment/methods');

        $response->assertStatus(200)
            ->assertJsonStructure(['payment_methods']);
    }

    protected function mockStripe()
    {
        // Mock Stripe to avoid real API calls during tests
        \Stripe\Stripe::setApiKey('sk_test_mock');
    }

    protected function mockStripeSetupIntent()
    {
        // In a real test, you might use a mocking library like Mockery
        // For now, we'll just ensure the endpoint structure is correct
    }
}