<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiVersioning
{
    /**
     * Supported API versions.
     */
    protected array $supportedVersions = ['1.0', '1.1', '2.0'];
    
    /**
     * Current stable version.
     */
    protected string $currentVersion = '1.0';
    
    /**
     * Deprecated versions.
     */
    protected array $deprecatedVersions = [];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $version = $this->resolveVersion($request);
        
        // Validate version
        if (!$this->isVersionSupported($version)) {
            return $this->handleUnsupportedVersion($version);
        }
        
        // Check for deprecated version
        if ($this->isVersionDeprecated($version)) {
            $this->addDeprecationWarning($version);
        }
        
        // Set version in request for use by controllers and resources
        $request->merge(['api_version' => $version]);
        config(['api.version' => $version]);
        
        $response = $next($request);
        
        // Add version headers to response
        $this->addVersionHeaders($response, $version);
        
        return $response;
    }

    /**
     * Resolve API version from request.
     */
    protected function resolveVersion(Request $request): string
    {
        // 1. Check Accept header (preferred method)
        $acceptHeader = $request->header('Accept');
        if ($acceptHeader && preg_match('/application\/vnd\.api\.v([0-9\.]+)\+json/', $acceptHeader, $matches)) {
            return $matches[1];
        }
        
        // 2. Check custom version header
        if ($request->hasHeader('X-API-Version')) {
            return $request->header('X-API-Version');
        }
        
        // 3. Check query parameter
        if ($request->query('api_version')) {
            return $request->query('api_version');
        }
        
        // 4. Check path prefix (e.g., /api/v2/users)
        $path = $request->path();
        if (preg_match('/^api\/v([0-9\.]+)\//', $path, $matches)) {
            return $matches[1];
        }
        
        // 5. Default to current version
        return $this->currentVersion;
    }

    /**
     * Check if version is supported.
     */
    protected function isVersionSupported(string $version): bool
    {
        return in_array($version, $this->supportedVersions);
    }

    /**
     * Check if version is deprecated.
     */
    protected function isVersionDeprecated(string $version): bool
    {
        return in_array($version, $this->deprecatedVersions);
    }

    /**
     * Handle unsupported version.
     */
    protected function handleUnsupportedVersion(string $version): JsonResponse
    {
        return response()->json([
            'error' => 'Unsupported API Version',
            'message' => "API version '{$version}' is not supported",
            'supported_versions' => $this->supportedVersions,
            'current_version' => $this->currentVersion,
            'code' => 'UNSUPPORTED_API_VERSION'
        ], 400);
    }

    /**
     * Add deprecation warning to session/logs.
     */
    protected function addDeprecationWarning(string $version): void
    {
        \Log::warning("Deprecated API version used", [
            'version' => $version,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'endpoint' => request()->path(),
        ]);
        
        // Could also trigger events for monitoring
    }

    /**
     * Add version headers to response.
     */
    protected function addVersionHeaders(Response $response, string $version): void
    {
        if ($response instanceof JsonResponse || $response->headers->get('Content-Type') === 'application/json') {
            $response->headers->set('X-API-Version', $version);
            $response->headers->set('X-API-Version-Current', $this->currentVersion);
            $response->headers->set('X-API-Versions-Supported', implode(',', $this->supportedVersions));
            
            if ($this->isVersionDeprecated($version)) {
                $response->headers->set('X-API-Deprecated', 'true');
                $response->headers->set('X-API-Deprecation-Info', 'This API version is deprecated. Please upgrade to v' . $this->currentVersion);
            }
        }
    }

    /**
     * Get version compatibility information.
     */
    public static function getVersionInfo(): array
    {
        $instance = new static();
        
        return [
            'current_version' => $instance->currentVersion,
            'supported_versions' => $instance->supportedVersions,
            'deprecated_versions' => $instance->deprecatedVersions,
            'version_resolution_order' => [
                'Accept header (application/vnd.api.v{version}+json)',
                'X-API-Version header',
                'api_version query parameter',
                'Path prefix (/api/v{version}/)',
                'Default version'
            ]
        ];
    }
}