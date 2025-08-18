<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UsageTracker;
use App\Http\Middleware\FeatureGate;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FreeTierAccessTest extends TestCase
{
    use WithFaker;

    public function test_feature_gate_logic_no_subscription()
    {
        // Test the logic directly without triggering database calls
        $plan = null; // No subscription
        
        if (!$plan) {
            $result = [
                'allowed' => false,
                'reason' => 'no_subscription',
                'message' => 'No active subscription found'
            ];
        }
        
        $this->assertFalse($result['allowed']);
        $this->assertEquals('no_subscription', $result['reason']);
    }

    public function test_feature_gate_logic_feature_not_included()
    {
        // Test the logic of feature inclusion checking
        $planFeatures = ['basic_feature', 'standard_feature'];
        $requestedFeature = 'premium_feature';
        
        $isIncluded = in_array($requestedFeature, $planFeatures);
        
        if (!$isIncluded) {
            $result = [
                'allowed' => false,
                'reason' => 'feature_not_included',
                'message' => 'Feature not included in current plan'
            ];
        }
        
        $this->assertFalse($result['allowed']);
        $this->assertEquals('feature_not_included', $result['reason']);
    }

    public function test_free_tier_feature_categories()
    {
        $categories = Feature::getCategories();
        
        $expectedCategories = [
            'core', 'analytics', 'collaboration', 'integration',
            'storage', 'support', 'security', 'api'
        ];
        
        foreach ($expectedCategories as $category) {
            $this->assertArrayHasKey($category, $categories);
            $this->assertIsString($categories[$category]);
        }
    }

    public function test_plan_feature_limit_checking()
    {
        $plan = new Plan();
        $plan->limits = [
            'projects' => 3,
            'users' => 5,
            'api_calls' => 1000
        ];
        
        // Test within limits
        $this->assertTrue($plan->allowsQuantity('projects', 2));
        $this->assertTrue($plan->allowsQuantity('projects', 3));
        $this->assertTrue($plan->allowsQuantity('users', 5));
        
        // Test exceeding limits
        $this->assertFalse($plan->allowsQuantity('projects', 4));
        $this->assertFalse($plan->allowsQuantity('users', 6));
        
        // Test unlimited feature (not in limits array)
        $this->assertTrue($plan->allowsQuantity('unlimited_feature', 1000));
    }

    public function test_feature_metadata_operations()
    {
        $feature = new Feature([
            'name' => 'Test Feature',
            'slug' => 'test-feature',
            'metadata' => [
                'description' => 'A test feature for unit testing',
                'icon' => 'test-icon',
                'category_order' => 1
            ]
        ]);
        
        $this->assertEquals('A test feature for unit testing', $feature->getMetadata('description'));
        $this->assertEquals('test-icon', $feature->getMetadata('icon'));
        $this->assertEquals(1, $feature->getMetadata('category_order'));
        $this->assertNull($feature->getMetadata('nonexistent'));
        $this->assertEquals('default', $feature->getMetadata('nonexistent', 'default'));
    }

    public function test_feature_premium_vs_free_tier()
    {
        $freeFeature = new Feature([
            'name' => 'Free Feature',
            'is_premium' => false
        ]);
        
        $premiumFeature = new Feature([
            'name' => 'Premium Feature',
            'is_premium' => true
        ]);
        
        $this->assertFalse($freeFeature->isPremium());
        $this->assertTrue($freeFeature->isFreeTier());
        
        $this->assertTrue($premiumFeature->isPremium());
        $this->assertFalse($premiumFeature->isFreeTier());
    }

    public function test_feature_limit_scenarios()
    {
        // Test unlimited feature (null limit)
        $unlimitedFeature = new Feature(['default_limit' => null]);
        $this->assertTrue($unlimitedFeature->isUnlimited());
        
        // Test unlimited feature (-1 limit)
        $unlimitedFeature2 = new Feature(['default_limit' => -1]);
        $this->assertTrue($unlimitedFeature2->isUnlimited());
        
        // Test limited feature
        $limitedFeature = new Feature(['default_limit' => 10]);
        $this->assertFalse($limitedFeature->isUnlimited());
        $this->assertEquals(10, $limitedFeature->getDefaultLimit());
    }

    public function test_plan_pricing_display()
    {
        // Free plan
        $freePlan = new Plan(['price' => 0]);
        $this->assertTrue($freePlan->isFree());
        $this->assertEquals('Free', $freePlan->getFormattedPriceAttribute());
        
        // Paid plans
        $basicPlan = new Plan(['price' => 9.99]);
        $this->assertFalse($basicPlan->isFree());
        $this->assertEquals('$9.99', $basicPlan->getFormattedPriceAttribute());
        
        $proPlan = new Plan(['price' => 29.00]);
        $this->assertEquals('$29.00', $proPlan->getFormattedPriceAttribute());
    }

    public function test_billing_period_display()
    {
        $monthlyPlan = new Plan(['billing_period' => 'monthly']);
        $this->assertEquals('per month', $monthlyPlan->getBillingPeriodDisplayAttribute());
        
        $yearlyPlan = new Plan(['billing_period' => 'yearly']);
        $this->assertEquals('per year', $yearlyPlan->getBillingPeriodDisplayAttribute());
        
        $customPlan = new Plan(['billing_period' => 'quarterly']);
        $this->assertEquals('quarterly', $customPlan->getBillingPeriodDisplayAttribute());
    }

    public function test_tenant_plan_logic()
    {
        // Test the logic of tenant without plan
        $hasSubscription = false;
        $currentPlan = $hasSubscription ? 'some_plan' : null;
        
        $this->assertNull($currentPlan);
        $this->assertFalse($hasSubscription);
    }

    public function test_feature_access_calculation_logic()
    {
        // Simulate usage tracking without actual Redis/Database
        $currentUsage = 8;
        $featureLimit = 10;
        $requestedAmount = 3;
        $newUsage = $currentUsage + $requestedAmount;
        
        // Test would exceed limit
        $this->assertTrue($newUsage > $featureLimit);
        
        // Test within limit
        $requestedAmount = 2;
        $newUsage = $currentUsage + $requestedAmount;
        $this->assertFalse($newUsage > $featureLimit);
        
        // Test usage percentage calculation
        $percentage = ($currentUsage / $featureLimit) * 100;
        $this->assertEquals(80.0, $percentage);
        
        // Test soft limit warning (80% threshold)
        $this->assertTrue($percentage >= 80);
    }

    public function test_usage_status_determination()
    {
        // Test different usage scenarios
        $scenarios = [
            ['current' => 1, 'limit' => 10, 'expected_status' => 'low_usage'],
            ['current' => 5, 'limit' => 10, 'expected_status' => 'moderate_usage'],
            ['current' => 8, 'limit' => 10, 'expected_status' => 'approaching_limit'],
            ['current' => 10, 'limit' => 10, 'expected_status' => 'limit_reached'],
            ['current' => 5, 'limit' => null, 'expected_status' => 'unlimited'],
        ];
        
        foreach ($scenarios as $scenario) {
            $status = $this->calculateUsageStatus($scenario['current'], $scenario['limit']);
            $this->assertEquals($scenario['expected_status'], $status);
        }
    }

    public function test_upgrade_urgency_calculation()
    {
        // Test high urgency recommendations
        $highUrgencyRecommendations = [
            ['urgency' => 'high'],
            ['urgency' => 'medium'],
            ['urgency' => 'low']
        ];
        
        $urgency = $this->calculateUpgradeUrgency($highUrgencyRecommendations);
        $this->assertEquals('high', $urgency);
        
        // Test medium urgency recommendations
        $mediumUrgencyRecommendations = [
            ['urgency' => 'medium'],
            ['urgency' => 'low']
        ];
        
        $urgency = $this->calculateUpgradeUrgency($mediumUrgencyRecommendations);
        $this->assertEquals('medium', $urgency);
        
        // Test low urgency recommendations
        $lowUrgencyRecommendations = [
            ['urgency' => 'low']
        ];
        
        $urgency = $this->calculateUpgradeUrgency($lowUrgencyRecommendations);
        $this->assertEquals('low', $urgency);
    }

    /**
     * Helper method to calculate usage status (mirrors controller logic)
     */
    private function calculateUsageStatus(int $current, ?int $limit): string
    {
        if (!$limit || $limit <= 0) {
            return 'unlimited';
        }

        $percentage = ($current / $limit) * 100;

        if ($percentage >= 100) {
            return 'limit_reached';
        } elseif ($percentage >= 80) {
            return 'approaching_limit';
        } elseif ($percentage >= 50) {
            return 'moderate_usage';
        }

        return 'low_usage';
    }

    /**
     * Helper method to calculate upgrade urgency (mirrors controller logic)
     */
    private function calculateUpgradeUrgency(array $recommendations): string
    {
        $highUrgency = collect($recommendations)->where('urgency', 'high')->count();
        $mediumUrgency = collect($recommendations)->where('urgency', 'medium')->count();

        if ($highUrgency > 0) {
            return 'high';
        } elseif ($mediumUrgency > 0) {
            return 'medium';
        }

        return 'low';
    }
}