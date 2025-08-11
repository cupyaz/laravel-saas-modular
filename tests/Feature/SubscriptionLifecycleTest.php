<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\RetentionOffer;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;
    protected Plan $basicPlan;
    protected Plan $premiumPlan;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create(['user_id' => $this->user->id]);
        
        $this->basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'price' => 9.99,
            'stripe_price_id' => 'price_basic_test'
        ]);
        
        $this->premiumPlan = Plan::factory()->create([
            'name' => 'Premium', 
            'price' => 29.99,
            'stripe_price_id' => 'price_premium_test'
        ]);
        
        $this->subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'internal_status' => Subscription::STATE_ACTIVE,
        ]);
    }

    public function test_user_can_view_their_subscriptions()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/subscriptions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subscriptions' => [
                    '*' => [
                        'id',
                        'status',
                        'internal_status',
                        'plan' => ['id', 'name', 'price', 'formatted_price'],
                        'is_active',
                        'is_cancelled',
                        'is_paused',
                    ]
                ],
                'tenant'
            ]);
    }

    public function test_user_can_change_subscription_plan()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/subscriptions/{$this->subscription->id}/change-plan", [
            'plan_id' => $this->premiumPlan->id,
            'prorate' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'subscription' => [
                    'id',
                    'plan',
                    'proration'
                ]
            ]);

        // Verify the subscription was updated
        $this->subscription->refresh();
        $this->assertEquals($this->premiumPlan->id, $this->subscription->plan_id);
    }

    public function test_user_cannot_change_to_same_plan()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/subscriptions/{$this->subscription->id}/change-plan", [
            'plan_id' => $this->basicPlan->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Subscription is already on this plan'
            ]);
    }

    public function test_user_can_pause_active_subscription()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/subscriptions/{$this->subscription->id}/pause");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'subscription' => ['id', 'status', 'paused_at']
            ]);

        $this->subscription->refresh();
        $this->assertEquals(Subscription::STATE_PAUSED, $this->subscription->internal_status);
        $this->assertNotNull($this->subscription->paused_at);
    }

    public function test_user_can_resume_paused_subscription()
    {
        // First pause the subscription
        $this->subscription->update([
            'internal_status' => Subscription::STATE_PAUSED,
            'paused_at' => now(),
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/subscriptions/{$this->subscription->id}/resume");

        $response->assertStatus(200);

        $this->subscription->refresh();
        $this->assertEquals(Subscription::STATE_ACTIVE, $this->subscription->internal_status);
        $this->assertNull($this->subscription->paused_at);
    }

    public function test_user_can_cancel_subscription_with_retention_offer()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/subscriptions/{$this->subscription->id}/cancel", [
            'reason' => 'too_expensive',
            'feedback' => [
                ['category' => 'pricing', 'comment' => 'Too expensive for my needs']
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'subscription',
                'retention_offer' // Should include retention offer
            ]);

        $this->subscription->refresh();
        $this->assertEquals(Subscription::STATE_CANCELLED, $this->subscription->internal_status);
        $this->assertNotNull($this->subscription->ends_at);
        $this->assertEquals('too_expensive', $this->subscription->cancellation_reason);

        // Verify retention offer was created
        $this->assertTrue(RetentionOffer::where('subscription_id', $this->subscription->id)->exists());
    }

    public function test_user_can_reactivate_cancelled_subscription_in_grace_period()
    {
        // Cancel the subscription first
        $this->subscription->update([
            'internal_status' => Subscription::STATE_CANCELLED,
            'ends_at' => now()->addDays(30),
            'grace_period_ends_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/subscriptions/{$this->subscription->id}/reactivate");

        $response->assertStatus(200);

        $this->subscription->refresh();
        $this->assertEquals(Subscription::STATE_ACTIVE, $this->subscription->internal_status);
        $this->assertNull($this->subscription->ends_at);
        $this->assertNull($this->subscription->grace_period_ends_at);
    }

    public function test_user_can_accept_retention_offer()
    {
        // Create a cancelled subscription with retention offer
        $this->subscription->update([
            'internal_status' => Subscription::STATE_CANCELLED,
            'ends_at' => now()->addDays(30),
            'grace_period_ends_at' => now()->addDays(30),
        ]);

        $retentionOffer = RetentionOffer::create([
            'subscription_id' => $this->subscription->id,
            'tenant_id' => $this->tenant->id,
            'offer_type' => RetentionOffer::TYPE_DISCOUNT,
            'discount_type' => RetentionOffer::DISCOUNT_PERCENTAGE,
            'discount_value' => 25,
            'offer_description' => 'Get 25% off your next 3 months',
            'valid_until' => now()->addHours(72),
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/subscriptions/{$this->subscription->id}/offers/{$retentionOffer->id}/accept");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'subscription',
                'offer'
            ]);

        // Verify offer was accepted
        $retentionOffer->refresh();
        $this->assertTrue($retentionOffer->is_accepted);
        $this->assertNotNull($retentionOffer->accepted_at);

        // Verify subscription was reactivated
        $this->subscription->refresh();
        $this->assertEquals(Subscription::STATE_ACTIVE, $this->subscription->internal_status);
    }

    public function test_user_cannot_accept_expired_retention_offer()
    {
        $retentionOffer = RetentionOffer::create([
            'subscription_id' => $this->subscription->id,
            'tenant_id' => $this->tenant->id,
            'offer_type' => RetentionOffer::TYPE_DISCOUNT,
            'discount_type' => RetentionOffer::DISCOUNT_PERCENTAGE,
            'discount_value' => 25,
            'offer_description' => 'Expired offer',
            'valid_until' => now()->subHour(), // Expired
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/subscriptions/{$this->subscription->id}/offers/{$retentionOffer->id}/accept");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'This offer has expired or is no longer valid'
            ]);
    }

    public function test_user_cannot_access_other_users_subscriptions()
    {
        $otherUser = User::factory()->create();
        $otherTenant = Tenant::factory()->create(['user_id' => $otherUser->id]);
        $otherSubscription = Subscription::factory()->create([
            'tenant_id' => $otherTenant->id,
            'plan_id' => $this->basicPlan->id,
        ]);

        Sanctum::actingAs($this->user);

        // Try to access other user's subscription
        $response = $this->getJson("/api/v1/subscriptions/{$otherSubscription->id}");
        $response->assertStatus(404);

        // Try to cancel other user's subscription
        $response = $this->postJson("/api/v1/subscriptions/{$otherSubscription->id}/cancel");
        $response->assertStatus(404);
    }

    public function test_subscription_state_transitions_are_validated()
    {
        // Try to pause an already cancelled subscription
        $this->subscription->update([
            'internal_status' => Subscription::STATE_CANCELLED,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/subscriptions/{$this->subscription->id}/pause");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Cannot pause subscription in current state'
            ]);
    }

    public function test_plan_comparison_endpoint()
    {
        $response = $this->postJson('/api/v1/plans/compare', [
            'plan_ids' => [$this->basicPlan->id, $this->premiumPlan->id]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'plans' => [
                    '*' => [
                        'id',
                        'name',
                        'price',
                        'features',
                        'limits'
                    ]
                ],
                'feature_list',
                'limit_list'
            ]);
    }

    public function test_plan_recommendations_for_authenticated_user()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/plans/recommendations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_plan',
                'recommendations' => [
                    '*' => [
                        'plan',
                        'proration',
                        'upgrade_reasons'
                    ]
                ]
            ]);
    }

    public function test_proration_calculation()
    {
        // Test the proration calculation method
        $proration = $this->subscription->calculateProration($this->premiumPlan);
        
        $this->assertArrayHasKey('amount', $proration);
        $this->assertArrayHasKey('description', $proration);
        $this->assertArrayHasKey('days_remaining', $proration);
        
        // Price difference should be positive for upgrade
        $this->assertGreaterThan(0, $proration['amount']);
    }
}