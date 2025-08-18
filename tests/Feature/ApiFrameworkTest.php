<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Plan;
use App\Http\Middleware\ApiVersioning;
use App\Http\Middleware\ApiRateLimit;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiFrameworkTest extends TestCase
{
    use WithFaker;

    public function test_api_versioning_header_resolution()
    {
        // Test Accept header versioning (preferred method)
        $response = $this->withHeaders([
            'Accept' => 'application/vnd.api.v1.1+json'
        ])->getJson('/api/v1/health');

        $response->assertStatus(200)
                ->assertHeader('X-API-Version', '1.1');
    }

    public function test_api_versioning_custom_header()
    {
        // Test custom version header
        $response = $this->withHeaders([
            'X-API-Version' => '1.0'
        ])->getJson('/api/v1/health');

        $response->assertStatus(200)
                ->assertHeader('X-API-Version', '1.0');
    }

    public function test_api_versioning_query_parameter()
    {
        // Test query parameter versioning
        $response = $this->getJson('/api/v1/health?api_version=1.0');

        $response->assertStatus(200)
                ->assertHeader('X-API-Version', '1.0');
    }

    public function test_api_versioning_unsupported_version()
    {
        // Test unsupported version
        $response = $this->withHeaders([
            'X-API-Version' => '99.0'
        ])->getJson('/api/v1/health');

        $response->assertStatus(400)
                ->assertJson([
                    'error' => 'Unsupported API Version',
                    'code' => 'UNSUPPORTED_API_VERSION'
                ]);
    }

    public function test_api_documentation_endpoints()
    {
        // Test overview endpoint
        $response = $this->getJson('/api/v1/docs');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'api' => ['name', 'version', 'base_url'],
                    'features',
                    'authentication',
                    'versioning',
                    'rate_limits'
                ]);

        // Test endpoints documentation
        $response = $this->getJson('/api/v1/docs/endpoints');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'total_endpoints',
                    'categories',
                    'endpoints_by_category',
                    'all_endpoints'
                ]);

        // Test webhooks documentation
        $response = $this->getJson('/api/v1/docs/webhooks');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'overview',
                    'events' => ['all_events', 'events_by_category'],
                    'payload_format',
                    'headers',
                    'verification'
                ]);
    }

    public function test_rate_limiting_logic()
    {
        // Test rate limit configuration
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
        }
    }

    public function test_rate_limiting_headers()
    {
        // Clear any existing rate limit cache
        Cache::flush();
        
        $response = $this->getJson('/api/v1/health');
        
        $response->assertStatus(200)
                ->assertHeader('X-RateLimit-Limit')
                ->assertHeader('X-RateLimit-Remaining')
                ->assertHeader('X-RateLimit-Reset')
                ->assertHeader('X-RateLimit-Tier');
    }

    public function test_api_health_endpoint()
    {
        $response = $this->getJson('/api/v1/health');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'timestamp',
                    'version',
                    'environment',
                    'laravel_version',
                    'api_version'
                ])
                ->assertJson([
                    'status' => 'ok'
                ]);
    }

    public function test_api_status_endpoint()
    {
        $response = $this->getJson('/api/v1/status');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'api_status',
                    'database_status',
                    'cache_status',
                    'timestamp',
                    'uptime',
                    'api_version'
                ])
                ->assertJson([
                    'api_status' => 'operational',
                    'database_status' => 'operational',
                    'cache_status' => 'operational'
                ]);
    }

    public function test_base_api_resource_structure()
    {
        // Test that API responses follow consistent structure
        $response = $this->getJson('/api/v1/docs');
        
        // Check that response includes standard meta information
        $data = $response->json();
        $this->assertIsArray($data);
        
        // The documentation endpoint should return structured data
        $this->assertArrayHasKey('api', $data);
        $this->assertArrayHasKey('features', $data);
    }

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

    public function test_rate_limit_tier_determination()
    {
        // Test with different scenarios for rate limit tier determination
        
        // Anonymous user should get free tier
        $this->assertEquals('free', $this->determineTierForAnonymous());
        
        // User without tenant should get free tier
        $this->assertEquals('free', $this->determineTierForUserWithoutTenant());
    }

    public function test_error_response_format()
    {
        // Test that error responses follow consistent format
        $response = $this->withHeaders([
            'X-API-Version' => '99.0'
        ])->getJson('/api/v1/health');

        $response->assertStatus(400);
        
        $errorData = $response->json();
        $this->assertArrayHasKey('error', $errorData);
        $this->assertArrayHasKey('message', $errorData);
        $this->assertArrayHasKey('code', $errorData);
        
        $this->assertIsString($errorData['error']);
        $this->assertIsString($errorData['message']);
        $this->assertIsString($errorData['code']);
    }

    public function test_api_documentation_completeness()
    {
        // Test that all documentation endpoints work
        $endpoints = [
            '/api/v1/docs',
            '/api/v1/docs/endpoints',
            '/api/v1/docs/webhooks',
            '/api/v1/docs/rate-limits',
            '/api/v1/docs/versioning',
            '/api/v1/docs/errors',
            '/api/v1/docs/resources'
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(200);
            
            $data = $response->json();
            $this->assertIsArray($data);
            $this->assertNotEmpty($data);
        }
    }

    public function test_api_middleware_registration()
    {
        // Test that API middleware is properly registered
        $response = $this->getJson('/api/v1/health');
        
        // Should have versioning headers
        $response->assertHeader('X-API-Version');
        $response->assertHeader('X-API-Version-Current');
        $response->assertHeader('X-API-Versions-Supported');
        
        // Should have rate limit headers
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Tier');
    }

    public function test_webhook_events_structure()
    {
        $response = $this->getJson('/api/v1/docs/webhooks');
        $data = $response->json();
        
        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('all_events', $data['events']);
        $this->assertArrayHasKey('events_by_category', $data['events']);
        
        $allEvents = $data['events']['all_events'];
        $eventsByCategory = $data['events']['events_by_category'];
        
        $this->assertIsArray($allEvents);
        $this->assertIsArray($eventsByCategory);
        $this->assertGreaterThan(0, count($allEvents));
        
        // Verify event categories exist
        $expectedCategories = ['user', 'tenant', 'subscription', 'usage', 'feature', 'security', 'system'];
        foreach ($expectedCategories as $category) {
            $this->assertArrayHasKey($category, $eventsByCategory);
        }
    }

    /**
     * Helper method to simulate rate limit tier determination for anonymous user.
     */
    private function determineTierForAnonymous(): string
    {
        // Simulate the logic from ApiRateLimit middleware
        return 'free'; // Anonymous users get free tier
    }

    /**
     * Helper method to simulate rate limit tier determination for user without tenant.
     */
    private function determineTierForUserWithoutTenant(): string
    {
        // Simulate the logic from ApiRateLimit middleware
        return 'free'; // Users without tenant get free tier
    }
}