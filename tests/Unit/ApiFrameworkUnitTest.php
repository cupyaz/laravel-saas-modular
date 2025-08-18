<?php

namespace Tests\Unit;

use App\Http\Middleware\ApiVersioning;
use App\Http\Middleware\ApiRateLimit;
use App\Models\Webhook;
use PHPUnit\Framework\TestCase;

class ApiFrameworkUnitTest extends TestCase
{
    public function test_api_versioning_configuration()
    {
        $versionInfo = ApiVersioning::getVersionInfo();
        
        $this->assertIsArray($versionInfo);
        $this->assertArrayHasKey('current_version', $versionInfo);
        $this->assertArrayHasKey('supported_versions', $versionInfo);
        $this->assertArrayHasKey('deprecated_versions', $versionInfo);
        $this->assertArrayHasKey('version_resolution_order', $versionInfo);
        
        // Check that current version is in supported versions
        $this->assertContains(
            $versionInfo['current_version'], 
            $versionInfo['supported_versions']
        );
        
        // Check version resolution order is properly defined
        $this->assertIsArray($versionInfo['version_resolution_order']);
        $this->assertGreaterThan(0, count($versionInfo['version_resolution_order']));
    }

    public function test_rate_limit_configuration()
    {
        $config = ApiRateLimit::getRateLimitConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('free', $config);
        $this->assertArrayHasKey('basic', $config);
        $this->assertArrayHasKey('pro', $config);
        $this->assertArrayHasKey('enterprise', $config);
        
        // Verify structure of each tier
        foreach ($config as $tier => $limits) {
            $this->assertArrayHasKey('requests_per_minute', $limits);
            $this->assertArrayHasKey('requests_per_hour', $limits);
            $this->assertArrayHasKey('requests_per_day', $limits);
            
            $this->assertIsInt($limits['requests_per_minute']);
            $this->assertIsInt($limits['requests_per_hour']);
            $this->assertIsInt($limits['requests_per_day']);
            
            // Verify that limits increase with tiers
            $this->assertGreaterThan(0, $limits['requests_per_minute']);
            $this->assertGreaterThan(0, $limits['requests_per_hour']);
            $this->assertGreaterThan(0, $limits['requests_per_day']);
        }
    }

    public function test_webhook_events_constants()
    {
        $events = Webhook::getAvailableEvents();
        
        $this->assertIsArray($events);
        $this->assertGreaterThan(0, count($events));
        
        // Check that all events follow naming convention
        foreach ($events as $event) {
            $this->assertStringContainsString('.', $event);
            $parts = explode('.', $event);
            $this->assertCount(2, $parts);
            $this->assertNotEmpty($parts[0]); // category
            $this->assertNotEmpty($parts[1]); // action
        }
        
        // Check specific required events exist
        $requiredEvents = [
            'user.created',
            'user.updated',
            'tenant.created',
            'subscription.created',
            'usage.limit_exceeded',
            'feature.access_denied',
        ];
        
        foreach ($requiredEvents as $requiredEvent) {
            $this->assertContains($requiredEvent, $events);
        }
    }

    public function test_webhook_events_by_category()
    {
        $eventsByCategory = Webhook::getEventsByCategory();
        
        $this->assertIsArray($eventsByCategory);
        $this->assertArrayHasKey('user', $eventsByCategory);
        $this->assertArrayHasKey('tenant', $eventsByCategory);
        $this->assertArrayHasKey('subscription', $eventsByCategory);
        $this->assertArrayHasKey('usage', $eventsByCategory);
        $this->assertArrayHasKey('feature', $eventsByCategory);
        $this->assertArrayHasKey('security', $eventsByCategory);
        $this->assertArrayHasKey('system', $eventsByCategory);
        
        // Verify each category has events
        foreach ($eventsByCategory as $category => $events) {
            $this->assertIsArray($events);
            $this->assertGreaterThan(0, count($events));
            
            // Verify all events in category start with category name
            foreach ($events as $event) {
                $this->assertStringStartsWith($category . '.', $event);
            }
        }
    }

    public function test_webhook_signature_generation()
    {
        $webhook = new Webhook();
        $webhook->secret = 'test-secret-key';
        
        $payload = ['test' => 'data', 'number' => 123];
        $signature = $webhook->generateSignature($payload);
        
        $this->assertStringStartsWith('sha256=', $signature);
        $this->assertEquals(71, strlen($signature)); // sha256= + 64 char hash
        
        // Test that same payload generates same signature
        $signature2 = $webhook->generateSignature($payload);
        $this->assertEquals($signature, $signature2);
        
        // Test that different payload generates different signature
        $differentPayload = ['test' => 'different', 'number' => 456];
        $differentSignature = $webhook->generateSignature($differentPayload);
        $this->assertNotEquals($signature, $differentSignature);
    }

    public function test_webhook_signature_verification()
    {
        $webhook = new Webhook();
        $webhook->secret = 'test-secret-key';
        
        $payload = ['test' => 'data', 'number' => 123];
        $validSignature = $webhook->generateSignature($payload);
        
        // Test valid signature
        $this->assertTrue($webhook->verifySignature($validSignature, $payload));
        
        // Test invalid signature
        $invalidSignature = 'sha256=invalidhash';
        $this->assertFalse($webhook->verifySignature($invalidSignature, $payload));
        
        // Test tampered payload
        $tamperedPayload = ['test' => 'tampered', 'number' => 123];
        $this->assertFalse($webhook->verifySignature($validSignature, $tamperedPayload));
    }

    public function test_webhook_event_matching()
    {
        $webhook = new Webhook();
        $webhook->is_active = true; // Must be active to receive events
        
        // Test exact match
        $webhook->events = ['user.created', 'user.updated'];
        $this->assertTrue($webhook->shouldReceiveEvent('user.created'));
        $this->assertTrue($webhook->shouldReceiveEvent('user.updated'));
        $this->assertFalse($webhook->shouldReceiveEvent('user.deleted'));
        
        // Test with inactive webhook
        $webhook->is_active = false;
        $this->assertFalse($webhook->shouldReceiveEvent('user.created'));
        
        // Test with no events specified (should receive all) - only if is_active
        $webhook->is_active = true;
        $webhook->events = [];
        $this->assertTrue($webhook->shouldReceiveEvent('user.created'));
        $this->assertTrue($webhook->shouldReceiveEvent('any.event'));
        
        // Test with null events (should receive all) - only if is_active
        $webhook->events = null;
        $this->assertTrue($webhook->shouldReceiveEvent('user.created'));
    }

    public function test_rate_limit_tier_ordering()
    {
        $config = ApiRateLimit::getRateLimitConfig();
        
        // Verify that higher tiers have higher limits
        $this->assertLessThan(
            $config['basic']['requests_per_minute'],
            $config['free']['requests_per_minute']
        );
        
        $this->assertLessThan(
            $config['pro']['requests_per_minute'],
            $config['basic']['requests_per_minute']
        );
        
        $this->assertLessThan(
            $config['enterprise']['requests_per_minute'],
            $config['pro']['requests_per_minute']
        );
    }

    public function test_api_constants_and_configurations()
    {
        // Test webhook status constants
        $this->assertEquals('active', Webhook::STATUS_ACTIVE);
        $this->assertEquals('inactive', Webhook::STATUS_INACTIVE);
        $this->assertEquals('failed', Webhook::STATUS_FAILED);
        $this->assertEquals('suspended', Webhook::STATUS_SUSPENDED);
        
        // Test that constants are consistent
        $this->assertIsString(Webhook::STATUS_ACTIVE);
        $this->assertIsString(Webhook::STATUS_INACTIVE);
        $this->assertIsString(Webhook::STATUS_FAILED);
        $this->assertIsString(Webhook::STATUS_SUSPENDED);
    }

    public function test_webhook_model_methods()
    {
        $webhook = new Webhook([
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'events' => ['user.created', 'user.updated'],
            'is_active' => true,
            'timeout' => 30,
            'retry_count' => 3,
        ]);
        
        // Test handlesEvent method
        $this->assertTrue($webhook->handlesEvent('user.created'));
        $this->assertFalse($webhook->handlesEvent('user.deleted'));
        
        // Test shouldReceiveEvent method
        $this->assertTrue($webhook->shouldReceiveEvent('user.created'));
        $this->assertFalse($webhook->shouldReceiveEvent('user.deleted'));
        
        // Test when webhook is inactive
        $webhook->is_active = false;
        $this->assertFalse($webhook->shouldReceiveEvent('user.created'));
    }

    public function test_webhook_failure_tracking()
    {
        $webhook = new Webhook([
            'failure_count' => 0,
        ]);
        
        // Test initial state
        $this->assertFalse($webhook->shouldBeDisabled());
        
        // Test failure threshold
        $webhook->failure_count = 10;
        $this->assertTrue($webhook->shouldBeDisabled());
        
        $webhook->failure_count = 9;
        $this->assertFalse($webhook->shouldBeDisabled());
    }
}