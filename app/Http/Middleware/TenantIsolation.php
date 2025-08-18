<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TenantIsolation
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip tenant isolation for certain routes
        if ($this->shouldSkipTenantIsolation($request)) {
            return $next($request);
        }

        // Resolve tenant from request
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return $this->handleNoTenant($request);
        }

        // Validate tenant status and security
        if (!$this->validateTenantAccess($tenant, $request)) {
            return $this->handleUnauthorizedAccess($tenant, $request);
        }

        // Initialize tenant context
        $this->initializeTenantContext($tenant, $request);

        // Set up tenant database connection
        $this->setupTenantDatabase($tenant);

        // Log tenant access if audit is enabled
        if ($tenant->isAuditEnabled()) {
            $this->logTenantAccess($tenant, $request);
        }

        // Validate resource access limits
        if (!$this->validateResourceLimits($tenant, $request)) {
            return $this->handleResourceLimitExceeded($tenant, $request);
        }

        $response = $next($request);

        // Add security headers to response
        $response = $this->addSecurityHeaders($response, $tenant);

        return $response;
    }

    /**
     * Check if tenant isolation should be skipped for this request
     */
    private function shouldSkipTenantIsolation(Request $request): bool
    {
        $skipRoutes = [
            'api/v1/health',
            'api/v1/status',
            'login',
            'register',
            'password/reset',
            'api/webhooks',
            'admin/tenants', // Super admin routes
        ];

        $path = $request->path();
        
        foreach ($skipRoutes as $skipRoute) {
            if (str_starts_with($path, $skipRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve tenant from request
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        // Method 1: From subdomain
        $tenant = $this->resolveTenantFromSubdomain($request);
        if ($tenant) {
            return $tenant;
        }

        // Method 2: From custom domain
        $tenant = $this->resolveTenantFromDomain($request);
        if ($tenant) {
            return $tenant;
        }

        // Method 3: From authenticated user
        $tenant = $this->resolveTenantFromUser($request);
        if ($tenant) {
            return $tenant;
        }

        // Method 4: From API header
        $tenant = $this->resolveTenantFromHeader($request);
        if ($tenant) {
            return $tenant;
        }

        return null;
    }

    /**
     * Resolve tenant from subdomain
     */
    private function resolveTenantFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        if (count($parts) >= 2) {
            $subdomain = $parts[0];
            
            // Skip if it's www or api subdomain
            if (in_array($subdomain, ['www', 'api', 'admin'])) {
                return null;
            }
            
            return Tenant::where('slug', $subdomain)->active()->first();
        }

        return null;
    }

    /**
     * Resolve tenant from custom domain
     */
    private function resolveTenantFromDomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        return Tenant::where('domain', $host)->active()->first();
    }

    /**
     * Resolve tenant from authenticated user
     */
    private function resolveTenantFromUser(Request $request): ?Tenant
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        // Check if user has a primary tenant
        if ($user->tenant_id) {
            return Tenant::find($user->tenant_id);
        }

        // Check user's tenant memberships
        $tenants = $user->tenants()->active()->get();
        if ($tenants->count() === 1) {
            return $tenants->first();
        }

        // If multiple tenants, try to resolve from session or header
        $tenantId = session('current_tenant_id') ?? $request->header('X-Tenant-ID');
        if ($tenantId && $tenants->contains('id', $tenantId)) {
            return $tenants->find($tenantId);
        }

        return null;
    }

    /**
     * Resolve tenant from API header
     */
    private function resolveTenantFromHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-ID');
        if (!$tenantId) {
            return null;
        }

        return Tenant::where('id', $tenantId)->active()->first();
    }

    /**
     * Validate tenant access
     */
    private function validateTenantAccess(Tenant $tenant, Request $request): bool
    {
        // Check if tenant is active
        if (!$tenant->isActive()) {
            $this->logSecurityEvent($tenant, 'inactive_tenant_access_attempt', [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return false;
        }

        // Check data residency requirements
        if (!$this->validateDataResidency($tenant, $request)) {
            return false;
        }

        // Check user access to tenant
        if (!$this->validateUserTenantAccess($tenant, $request)) {
            return false;
        }

        return true;
    }

    /**
     * Validate data residency requirements
     */
    private function validateDataResidency(Tenant $tenant, Request $request): bool
    {
        $tenantRegion = $tenant->data_residency;
        if (!$tenantRegion) {
            return true; // No residency requirements
        }

        $clientCountry = $this->getClientCountry($request);
        $allowedRegions = $this->getAllowedRegionsForCountry($clientCountry);

        if (!in_array($tenantRegion, $allowedRegions)) {
            $this->logSecurityEvent($tenant, 'data_residency_violation', [
                'tenant_region' => $tenantRegion,
                'client_country' => $clientCountry,
                'ip_address' => $request->ip(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Validate user access to tenant
     */
    private function validateUserTenantAccess(Tenant $tenant, Request $request): bool
    {
        $user = Auth::user();
        if (!$user) {
            return true; // Public access, will be handled by other middleware
        }

        // Check if user belongs to tenant
        if ($user->tenant_id === $tenant->id) {
            return true;
        }

        // Check tenant membership
        if ($user->tenants()->where('tenant_id', $tenant->id)->exists()) {
            return true;
        }

        // Log unauthorized access attempt
        $this->logSecurityEvent($tenant, 'unauthorized_tenant_access', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip_address' => $request->ip(),
        ]);

        return false;
    }

    /**
     * Initialize tenant context
     */
    private function initializeTenantContext(Tenant $tenant, Request $request): void
    {
        // Set tenant in application context
        app()->instance('tenant', $tenant);
        
        // Set tenant in request attributes
        $request->attributes->set('tenant', $tenant);
        
        // Set tenant in session
        session(['current_tenant_id' => $tenant->id]);
        
        // Set tenant in config for use by other services
        Config::set('tenant.current', $tenant);
        Config::set('tenant.id', $tenant->id);
        Config::set('tenant.name', $tenant->name);
        Config::set('tenant.encryption_key', $tenant->getEncryptionKey());
    }

    /**
     * Setup tenant database connection
     */
    private function setupTenantDatabase(Tenant $tenant): void
    {
        $connectionName = $tenant->getConnectionName();
        $config = $tenant->getDatabaseConfig();
        
        // Configure database connection
        Config::set("database.connections.{$connectionName}", $config);
        
        // Set as default connection for tenant context
        Config::set('database.default', $connectionName);
        
        // Reconnect with new configuration
        DB::purge($connectionName);
        DB::reconnect($connectionName);
        
        // Test connection
        try {
            DB::connection($connectionName)->getPdo();
        } catch (\Exception $e) {
            Log::error("Failed to connect to tenant database", [
                'tenant_id' => $tenant->id,
                'connection' => $connectionName,
                'error' => $e->getMessage(),
            ]);
            
            throw new \RuntimeException('Database connection failed for tenant');
        }
    }

    /**
     * Validate resource limits
     */
    private function validateResourceLimits(Tenant $tenant, Request $request): bool
    {
        // Check API rate limits
        if (!$this->validateApiRateLimit($tenant, $request)) {
            return false;
        }

        // Check storage limits
        if (!$this->validateStorageLimit($tenant)) {
            return false;
        }

        // Check user limits
        if (!$this->validateUserLimit($tenant)) {
            return false;
        }

        return true;
    }

    /**
     * Validate API rate limit
     */
    private function validateApiRateLimit(Tenant $tenant, Request $request): bool
    {
        $limit = $tenant->getResourceLimit('max_api_calls_per_hour');
        if (!$limit) {
            return true;
        }

        $key = "api_calls:tenant:{$tenant->id}:" . now()->format('Y-m-d-H');
        $current = cache()->get($key, 0);

        if ($current >= $limit) {
            $this->logSecurityEvent($tenant, 'api_rate_limit_exceeded', [
                'limit' => $limit,
                'current' => $current,
                'endpoint' => $request->path(),
            ]);
            return false;
        }

        // Increment counter
        cache()->put($key, $current + 1, now()->addHour());
        
        return true;
    }

    /**
     * Validate storage limit
     */
    private function validateStorageLimit(Tenant $tenant): bool
    {
        $limit = $tenant->getResourceLimit('max_storage_gb');
        if (!$limit) {
            return true;
        }

        // Calculate current storage usage (simplified)
        $currentUsageGB = 0; // This would calculate actual storage usage
        
        return $currentUsageGB < $limit;
    }

    /**
     * Validate user limit
     */
    private function validateUserLimit(Tenant $tenant): bool
    {
        $limit = $tenant->getResourceLimit('max_users');
        if (!$limit) {
            return true;
        }

        $currentUsers = $tenant->users()->count();
        
        return $currentUsers < $limit;
    }

    /**
     * Log tenant access
     */
    private function logTenantAccess(Tenant $tenant, Request $request): void
    {
        $user = Auth::user();
        
        TenantAuditLog::logActivity(
            tenantId: $tenant->id,
            userId: $user?->id,
            action: TenantAuditLog::ACTION_READ,
            resourceType: 'tenant_access',
            metadata: [
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );
    }

    /**
     * Log security event
     */
    private function logSecurityEvent(Tenant $tenant, string $eventType, array $details = []): void
    {
        $user = Auth::user();
        
        TenantAuditLog::logSecurityEvent(
            tenantId: $tenant->id,
            userId: $user?->id,
            eventType: $eventType,
            details: $details,
            riskLevel: TenantAuditLog::RISK_HIGH
        );
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(Response $response, Tenant $tenant): Response
    {
        // Add tenant-specific security headers
        $response->headers->set('X-Tenant-ID', $tenant->id);
        $response->headers->set('X-Data-Residency', $tenant->data_residency);
        
        // Add compliance headers if required
        if ($tenant->hasCompliance(Tenant::COMPLIANCE_GDPR)) {
            $response->headers->set('X-GDPR-Compliant', 'true');
        }
        
        if ($tenant->hasCompliance(Tenant::COMPLIANCE_HIPAA)) {
            $response->headers->set('X-HIPAA-Compliant', 'true');
        }
        
        // Add security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        return $response;
    }

    /**
     * Handle no tenant found
     */
    private function handleNoTenant(Request $request): Response
    {
        Log::warning('No tenant resolved for request', [
            'path' => $request->path(),
            'host' => $request->getHost(),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'error' => 'Tenant not found',
            'message' => 'Unable to resolve tenant for this request',
            'code' => 'TENANT_NOT_FOUND'
        ], 404);
    }

    /**
     * Handle unauthorized access
     */
    private function handleUnauthorizedAccess(Tenant $tenant, Request $request): Response
    {
        return response()->json([
            'error' => 'Unauthorized access',
            'message' => 'Access to this tenant is not allowed',
            'code' => 'TENANT_ACCESS_DENIED'
        ], 403);
    }

    /**
     * Handle resource limit exceeded
     */
    private function handleResourceLimitExceeded(Tenant $tenant, Request $request): Response
    {
        return response()->json([
            'error' => 'Resource limit exceeded',
            'message' => 'Tenant has exceeded resource limits',
            'code' => 'RESOURCE_LIMIT_EXCEEDED'
        ], 429);
    }

    /**
     * Get client country from IP address
     */
    private function getClientCountry(Request $request): ?string
    {
        // This would integrate with a GeoIP service
        // For now, return null (no restriction)
        return null;
    }

    /**
     * Get allowed regions for country
     */
    private function getAllowedRegionsForCountry(?string $country): array
    {
        if (!$country) {
            return [
                Tenant::RESIDENCY_EU,
                Tenant::RESIDENCY_US,
                Tenant::RESIDENCY_CANADA,
                Tenant::RESIDENCY_AUSTRALIA,
                Tenant::RESIDENCY_ASIA,
            ];
        }

        $regionMap = [
            'US' => [Tenant::RESIDENCY_US],
            'CA' => [Tenant::RESIDENCY_CANADA, Tenant::RESIDENCY_US],
            'GB' => [Tenant::RESIDENCY_EU],
            'DE' => [Tenant::RESIDENCY_EU],
            'FR' => [Tenant::RESIDENCY_EU],
            'AU' => [Tenant::RESIDENCY_AUSTRALIA],
            'NZ' => [Tenant::RESIDENCY_AUSTRALIA],
            'JP' => [Tenant::RESIDENCY_ASIA],
            'SG' => [Tenant::RESIDENCY_ASIA],
        ];

        return $regionMap[$country] ?? [Tenant::RESIDENCY_US]; // Default to US
    }
}