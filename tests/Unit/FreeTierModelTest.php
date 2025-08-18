<?php

namespace Tests\Unit;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use PHPUnit\Framework\TestCase;

class FreeTierModelTest extends TestCase
{
    public function test_feature_constants_are_defined()
    {
        // Test category constants
        $this->assertEquals('core', Feature::CATEGORY_CORE);
        $this->assertEquals('analytics', Feature::CATEGORY_ANALYTICS);
        $this->assertEquals('collaboration', Feature::CATEGORY_COLLABORATION);
        $this->assertEquals('integration', Feature::CATEGORY_INTEGRATION);
        $this->assertEquals('storage', Feature::CATEGORY_STORAGE);
        $this->assertEquals('support', Feature::CATEGORY_SUPPORT);
        $this->assertEquals('security', Feature::CATEGORY_SECURITY);
        $this->assertEquals('api', Feature::CATEGORY_API);
    }

    public function test_feature_model_basic_functionality()
    {
        $feature = new Feature();
        $feature->name = 'Test Feature';
        $feature->slug = 'test-feature';
        $feature->description = 'A test feature';
        $feature->category = Feature::CATEGORY_CORE;
        $feature->is_premium = false;
        $feature->default_limit = 5;
        $feature->is_active = true;

        $this->assertEquals('Test Feature', $feature->name);
        $this->assertEquals('test-feature', $feature->slug);
        $this->assertEquals(Feature::CATEGORY_CORE, $feature->category);
        $this->assertFalse($feature->isPremium());
        $this->assertTrue($feature->isFreeTier());
        $this->assertEquals(5, $feature->getDefaultLimit());
        $this->assertFalse($feature->isUnlimited());
    }

    public function test_feature_unlimited_functionality()
    {
        $feature = new Feature();
        $feature->default_limit = null;
        $this->assertTrue($feature->isUnlimited());

        $feature->default_limit = -1;
        $this->assertTrue($feature->isUnlimited());

        $feature->default_limit = 10;
        $this->assertFalse($feature->isUnlimited());
    }

    public function test_feature_metadata_functionality()
    {
        $feature = new Feature();
        $feature->metadata = ['key1' => 'value1', 'key2' => 'value2'];

        $this->assertEquals('value1', $feature->getMetadata('key1'));
        $this->assertEquals('value2', $feature->getMetadata('key2'));
        $this->assertNull($feature->getMetadata('nonexistent'));
        $this->assertEquals('default', $feature->getMetadata('nonexistent', 'default'));
    }

    public function test_feature_categories_helper()
    {
        $categories = Feature::getCategories();
        
        $this->assertIsArray($categories);
        $this->assertArrayHasKey(Feature::CATEGORY_CORE, $categories);
        $this->assertArrayHasKey(Feature::CATEGORY_ANALYTICS, $categories);
        $this->assertEquals('Core Features', $categories[Feature::CATEGORY_CORE]);
    }

    public function test_plan_feature_pivot_functionality()
    {
        $planFeature = new PlanFeature();
        $planFeature->limit = 10;
        $planFeature->is_included = true;

        $this->assertFalse($planFeature->isUnlimited());
        $this->assertTrue($planFeature->allowsQuantity(5));
        $this->assertTrue($planFeature->allowsQuantity(10));
        $this->assertFalse($planFeature->allowsQuantity(15));
        $this->assertEquals(10, $planFeature->getEffectiveLimit());
    }

    public function test_plan_feature_unlimited_functionality()
    {
        $planFeature = new PlanFeature();
        $planFeature->limit = null;
        $planFeature->is_included = true;

        $this->assertTrue($planFeature->isUnlimited());
        $this->assertTrue($planFeature->allowsQuantity(1000));
        $this->assertNull($planFeature->getEffectiveLimit());

        $planFeature->limit = -1;
        $this->assertTrue($planFeature->isUnlimited());
        $this->assertTrue($planFeature->allowsQuantity(1000));
    }

    public function test_plan_feature_not_included_functionality()
    {
        $planFeature = new PlanFeature();
        $planFeature->limit = 10;
        $planFeature->is_included = false;

        $this->assertFalse($planFeature->allowsQuantity(1));
        $this->assertEquals(0, $planFeature->getEffectiveLimit());
    }

    public function test_plan_has_feature_functionality()
    {
        // Mock plan with fillable attributes
        $plan = new Plan();
        $plan->name = 'Test Plan';
        $plan->price = 0;
        $plan->features = ['feature1', 'feature2', 'feature3'];

        $this->assertTrue($plan->hasFeature('feature1'));
        $this->assertTrue($plan->hasFeature('feature2'));
        $this->assertFalse($plan->hasFeature('nonexistent'));
    }

    public function test_plan_limit_functionality()
    {
        $plan = new Plan();
        $plan->limits = [
            'projects' => 5,
            'users' => 10,
            'storage' => 1000
        ];

        $this->assertEquals(5, $plan->getLimit('projects'));
        $this->assertEquals(10, $plan->getLimit('users'));
        $this->assertNull($plan->getLimit('nonexistent'));

        $this->assertTrue($plan->allowsQuantity('projects', 3));
        $this->assertTrue($plan->allowsQuantity('projects', 5));
        $this->assertFalse($plan->allowsQuantity('projects', 7));
        $this->assertTrue($plan->allowsQuantity('nonexistent', 1000)); // unlimited
    }

    public function test_plan_free_tier_detection()
    {
        $freePlan = new Plan();
        $freePlan->price = 0;
        $this->assertTrue($freePlan->isFree());

        $paidPlan = new Plan();
        $paidPlan->price = 9.99;
        $this->assertFalse($paidPlan->isFree());
    }

    public function test_plan_formatted_price()
    {
        $freePlan = new Plan();
        $freePlan->price = 0;
        $this->assertEquals('Free', $freePlan->getFormattedPriceAttribute());

        $paidPlan = new Plan();
        $paidPlan->price = 9.99;
        $this->assertEquals('$9.99', $paidPlan->getFormattedPriceAttribute());

        $paidPlan->price = 15;
        $this->assertEquals('$15.00', $paidPlan->getFormattedPriceAttribute());
    }

    public function test_plan_billing_period_display()
    {
        $monthlyPlan = new Plan();
        $monthlyPlan->billing_period = 'monthly';
        $this->assertEquals('per month', $monthlyPlan->getBillingPeriodDisplayAttribute());

        $yearlyPlan = new Plan();
        $yearlyPlan->billing_period = 'yearly';
        $this->assertEquals('per year', $yearlyPlan->getBillingPeriodDisplayAttribute());

        $customPlan = new Plan();
        $customPlan->billing_period = 'quarterly';
        $this->assertEquals('quarterly', $customPlan->getBillingPeriodDisplayAttribute());
    }
}