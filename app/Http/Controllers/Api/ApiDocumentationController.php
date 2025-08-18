<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ApiVersioning;
use App\Http\Middleware\ApiRateLimit;
use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ApiDocumentationController extends Controller
{
    /**
     * Get API documentation overview.
     */
    public function overview(): JsonResponse
    {
        return response()->json([
            'api' => [
                'name' => 'Laravel SaaS Platform API',
                'description' => 'Comprehensive REST API for multi-tenant SaaS platform with advanced features',
                'version' => config('api.version', '1.0'),
                'base_url' => url('/api/v1'),
                'documentation_url' => url('/api/docs'),
                'status' => 'stable',
            ],
            'features' => [
                'multi_tenant_isolation',
                'feature_gating',
                'usage_tracking',
                'subscription_management',
                'webhook_notifications',
                'rate_limiting',
                'api_versioning',
                'comprehensive_resources',
            ],
            'authentication' => [
                'type' => 'Bearer Token (Laravel Sanctum)',
                'header' => 'Authorization: Bearer {token}',
                'endpoints' => [
                    'login' => 'POST /api/v1/auth/login',
                    'register' => 'POST /api/v1/auth/register',
                    'refresh' => 'POST /api/v1/auth/refresh',
                ],
            ],
            'versioning' => ApiVersioning::getVersionInfo(),
            'rate_limits' => ApiRateLimit::getRateLimitConfig(),
        ]);
    }

    /**
     * Get all API endpoints with documentation.
     */
    public function endpoints(): JsonResponse
    {
        $routes = Route::getRoutes();
        $apiRoutes = [];

        foreach ($routes as $route) {
            $uri = $route->uri();
            
            // Only include API routes
            if (!str_starts_with($uri, 'api/v1/')) {
                continue;
            }

            $methods = $route->methods();
            // Remove HEAD and OPTIONS
            $methods = array_filter($methods, fn($method) => !in_array($method, ['HEAD', 'OPTIONS']));

            if (empty($methods)) {
                continue;
            }

            $apiRoutes[] = [
                'uri' => $uri,
                'methods' => array_values($methods),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $route->middleware(),
                'parameters' => $this->getRouteParameters($route),
                'description' => $this->getRouteDescription($route),
                'category' => $this->getRouteCategory($uri),
            ];
        }

        // Group by category
        $grouped = collect($apiRoutes)->groupBy('category');

        return response()->json([
            'total_endpoints' => count($apiRoutes),
            'categories' => $grouped->keys(),
            'endpoints_by_category' => $grouped,
            'all_endpoints' => $apiRoutes,
        ]);
    }

    /**
     * Get webhook events documentation.
     */
    public function webhooks(): JsonResponse
    {
        return response()->json([
            'overview' => [
                'description' => 'Real-time event notifications via HTTP callbacks',
                'setup_url' => '/api/v1/webhooks',
                'test_endpoint' => 'POST /api/v1/webhooks/{id}/ping',
                'signature_verification' => 'HMAC SHA256 with webhook secret',
            ],
            'events' => [
                'all_events' => Webhook::getAvailableEvents(),
                'events_by_category' => Webhook::getEventsByCategory(),
                'total_events' => count(Webhook::getAvailableEvents()),
            ],
            'payload_format' => [
                'event' => 'string - Event name (e.g., user.created)',
                'data' => 'object - Event-specific payload data',
                'webhook' => [
                    'id' => 'integer - Webhook ID',
                    'tenant_id' => 'integer - Tenant ID',
                ],
                'timestamp' => 'string - ISO 8601 timestamp',
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Laravel-SaaS-Webhook/1.0',
                'X-Webhook-Event' => 'Event name',
                'X-Webhook-Signature' => 'sha256=... (HMAC signature)',
                'X-Webhook-Timestamp' => 'Unix timestamp',
                'X-Webhook-ID' => 'Delivery ID',
            ],
            'verification' => [
                'algorithm' => 'HMAC SHA256',
                'header' => 'X-Webhook-Signature',
                'format' => 'sha256={signature}',
                'data' => 'JSON payload as string',
                'secret' => 'Webhook secret from webhook configuration',
            ],
        ]);
    }

    /**
     * Get rate limiting documentation.
     */
    public function rateLimits(): JsonResponse
    {
        return response()->json([
            'overview' => [
                'description' => 'API rate limiting based on subscription tier',
                'identifier' => 'Tenant ID > User ID > IP Address',
                'periods' => ['minute', 'hour', 'day'],
                'headers' => [
                    'X-RateLimit-Limit' => 'Limit for current period',
                    'X-RateLimit-Remaining' => 'Remaining requests',
                    'X-RateLimit-Reset' => 'Reset timestamp',
                    'X-RateLimit-Tier' => 'Current subscription tier',
                ],
            ],
            'tiers' => ApiRateLimit::getRateLimitConfig(),
            'exceeded_response' => [
                'status_code' => 429,
                'headers' => [
                    'Retry-After' => 'Seconds until reset',
                ],
                'body' => [
                    'error' => 'Rate Limit Exceeded',
                    'message' => 'Descriptive error message',
                    'tier' => 'Current tier',
                    'exceeded_limit' => 'Which limit was exceeded',
                    'retry_after' => 'Seconds to wait',
                    'limits' => 'Current limit status',
                    'upgrade_info' => 'Next tier information',
                ],
            ],
        ]);
    }

    /**
     * Get API versioning documentation.
     */
    public function versioning(): JsonResponse
    {
        return response()->json([
            'overview' => [
                'description' => 'API versioning with backward compatibility',
                'current_version' => config('api.version', '1.0'),
                'default_behavior' => 'Latest stable version',
            ],
            'version_resolution' => ApiVersioning::getVersionInfo(),
            'methods' => [
                'accept_header' => [
                    'header' => 'Accept: application/vnd.api.v{version}+json',
                    'example' => 'Accept: application/vnd.api.v1.0+json',
                    'priority' => 1,
                ],
                'version_header' => [
                    'header' => 'X-API-Version: {version}',
                    'example' => 'X-API-Version: 1.0',
                    'priority' => 2,
                ],
                'query_parameter' => [
                    'parameter' => 'api_version={version}',
                    'example' => '?api_version=1.0',
                    'priority' => 3,
                ],
                'path_prefix' => [
                    'format' => '/api/v{version}/',
                    'example' => '/api/v1.0/users',
                    'priority' => 4,
                ],
            ],
            'response_headers' => [
                'X-API-Version' => 'Used API version',
                'X-API-Version-Current' => 'Latest stable version',
                'X-API-Versions-Supported' => 'Comma-separated supported versions',
                'X-API-Deprecated' => 'true if version is deprecated',
            ],
        ]);
    }

    /**
     * Get error codes documentation.
     */
    public function errorCodes(): JsonResponse
    {
        return response()->json([
            'overview' => [
                'description' => 'Standardized error responses with consistent structure',
                'format' => 'All errors include error code, message, and context',
            ],
            'http_status_codes' => [
                '400' => 'Bad Request - Invalid request format or parameters',
                '401' => 'Unauthorized - Authentication required or invalid',
                '403' => 'Forbidden - Access denied (feature gates, permissions)',
                '404' => 'Not Found - Resource does not exist',
                '422' => 'Unprocessable Entity - Validation errors',
                '429' => 'Too Many Requests - Rate limit exceeded',
                '500' => 'Internal Server Error - Unexpected server error',
                '503' => 'Service Unavailable - Temporary service outage',
            ],
            'application_error_codes' => [
                'UNAUTHORIZED' => 'Authentication failure',
                'FEATURE_NOT_AVAILABLE' => 'Feature not included in current plan',
                'USAGE_LIMIT_EXCEEDED' => 'Feature usage limit reached',
                'RATE_LIMIT_EXCEEDED' => 'API rate limit exceeded',
                'UNSUPPORTED_API_VERSION' => 'API version not supported',
                'TENANT_NOT_FOUND' => 'Tenant context missing or invalid',
                'SUBSCRIPTION_REQUIRED' => 'Active subscription required',
                'INSUFFICIENT_PERMISSIONS' => 'User lacks required permissions',
                'VALIDATION_FAILED' => 'Request validation failed',
                'RESOURCE_NOT_FOUND' => 'Requested resource not found',
            ],
            'error_response_format' => [
                'error' => 'string - Human-readable error title',
                'message' => 'string - Detailed error description',
                'code' => 'string - Application error code',
                'details' => 'object - Additional error context (optional)',
                'meta' => 'object - Request metadata',
            ],
        ]);
    }

    /**
     * Get API resources documentation.
     */
    public function resources(): JsonResponse
    {
        return response()->json([
            'overview' => [
                'description' => 'Consistent resource representation across all endpoints',
                'format' => 'All resources include data, meta, and links sections',
            ],
            'resource_structure' => [
                'data' => 'object - Main resource data',
                'meta' => [
                    'api_version' => 'string - API version used',
                    'timestamp' => 'string - Response timestamp',
                    'request_id' => 'string - Unique request identifier',
                ],
                'links' => [
                    'self' => 'string - Link to current resource',
                    'related' => 'object - Links to related resources',
                ],
            ],
            'resource_types' => [
                'User' => [
                    'endpoint' => '/api/v1/users/{id}',
                    'fields' => ['id', 'name', 'email', 'email_verified', 'created_at', 'updated_at'],
                    'relationships' => ['tenants', 'current_tenant', 'profile'],
                ],
                'Tenant' => [
                    'endpoint' => '/api/v1/tenant',
                    'fields' => ['id', 'name', 'slug', 'domain', 'is_active', 'trial', 'subscription'],
                    'relationships' => ['users', 'subscription', 'current_plan'],
                ],
                'Plan' => [
                    'endpoint' => '/api/v1/plans/{id}',
                    'fields' => ['id', 'name', 'price', 'billing_period', 'features', 'limits'],
                    'relationships' => ['subscriptions', 'plan_features'],
                ],
                'Subscription' => [
                    'endpoint' => '/api/v1/subscriptions/{id}',
                    'fields' => ['id', 'status', 'current_period_start', 'current_period_end'],
                    'relationships' => ['tenant', 'plan', 'items'],
                ],
            ],
            'date_formats' => [
                'iso' => 'ISO 8601 string (2024-01-01T12:00:00Z)',
                'human' => 'Human-readable relative time (2 hours ago)',
                'formatted' => 'Formatted datetime (2024-01-01 12:00:00)',
                'timestamp' => 'Unix timestamp (1704110400)',
            ],
        ]);
    }

    /**
     * Get route parameters from route definition.
     */
    private function getRouteParameters($route): array
    {
        $uri = $route->uri();
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        
        return array_map(function ($param) {
            return [
                'name' => str_replace('?', '', $param),
                'required' => !str_contains($param, '?'),
                'type' => $this->guessParameterType($param),
            ];
        }, $matches[1] ?? []);
    }

    /**
     * Guess parameter type from name.
     */
    private function guessParameterType(string $param): string
    {
        $param = str_replace('?', '', $param);
        
        if (str_ends_with($param, '_id') || $param === 'id') {
            return 'integer';
        }
        
        if (in_array($param, ['slug', 'name', 'type'])) {
            return 'string';
        }
        
        return 'string';
    }

    /**
     * Get route description based on URI and method.
     */
    private function getRouteDescription($route): string
    {
        $uri = $route->uri();
        $methods = $route->methods();
        $method = $methods[0] ?? 'GET';
        
        // Extract resource name
        $parts = explode('/', $uri);
        $resource = $parts[2] ?? 'resource';
        
        return match ($method) {
            'GET' => str_contains($uri, '{') ? "Get specific {$resource}" : "List all {$resource}",
            'POST' => "Create new {$resource}",
            'PUT', 'PATCH' => "Update {$resource}",
            'DELETE' => "Delete {$resource}",
            default => "Manage {$resource}",
        };
    }

    /**
     * Get route category from URI.
     */
    private function getRouteCategory(string $uri): string
    {
        $parts = explode('/', $uri);
        
        if (count($parts) < 3) {
            return 'other';
        }
        
        $category = $parts[2];
        
        // Map some routes to more descriptive categories
        return match ($category) {
            'auth' => 'Authentication',
            'users' => 'User Management',
            'tenant' => 'Tenant Management',
            'plans' => 'Billing & Plans',
            'subscriptions' => 'Billing & Plans',
            'payment' => 'Payment Processing',
            'features' => 'Feature Management',
            'free-tier' => 'Free Tier',
            'usage' => 'Usage Tracking',
            'upgrade-prompts' => 'Conversion Optimization',
            'performance' => 'Performance Monitoring',
            'webhooks' => 'Webhooks',
            'examples' => 'API Examples',
            default => ucfirst($category),
        };
    }
}