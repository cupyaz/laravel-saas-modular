<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use App\Models\AdminPermission as PermissionModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class AdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): BaseResponse
    {
        $user = $request->user();

        // User must be authenticated and admin
        if (!$user || !$user->isAdmin()) {
            return $this->forbiddenResponse($request, 'Admin access required');
        }

        // Check if any of the required permissions are dangerous
        $dangerousPermissions = array_filter($permissions, function($permission) {
            return PermissionModel::isDangerous($permission);
        });

        // For dangerous permissions, require additional confirmation or logging
        if (!empty($dangerousPermissions)) {
            $this->logDangerousPermissionAttempt($user->id, $dangerousPermissions, $request);
            
            // Check if user has confirmed dangerous action (via session or request parameter)
            if (!$this->hasDangerousActionConfirmation($request)) {
                return $this->dangerousActionConfirmationRequired($request, $dangerousPermissions);
            }
        }

        // Check specific permissions
        if (!empty($permissions)) {
            $hasRequiredPermission = false;
            $missingPermissions = [];
            
            foreach ($permissions as $permission) {
                if ($user->hasAdminPermission($permission)) {
                    $hasRequiredPermission = true;
                } else {
                    $missingPermissions[] = $permission;
                }
            }

            // For multiple permissions, user needs at least one
            if (!$hasRequiredPermission) {
                AdminAuditLog::logAction(
                    $user->id,
                    'permission_denied',
                    "Access denied to {$request->url()} - missing permissions: " . implode(', ', $missingPermissions),
                    null,
                    [],
                    [],
                    [
                        'required_permissions' => $permissions,
                        'missing_permissions' => $missingPermissions,
                        'requested_url' => $request->url(),
                        'request_method' => $request->method(),
                    ],
                    AdminAuditLog::SEVERITY_WARNING
                );

                return $this->forbiddenResponse(
                    $request, 
                    'Missing required permissions: ' . implode(', ', $missingPermissions)
                );
            }
        }

        // Log access to sensitive endpoints
        if ($this->isSensitiveEndpoint($request)) {
            AdminAuditLog::logAction(
                $user->id,
                'sensitive_endpoint_access',
                "Accessed sensitive endpoint: {$request->url()}",
                null,
                [],
                [],
                [
                    'endpoint' => $request->url(),
                    'method' => $request->method(),
                    'permissions_used' => $permissions,
                ],
                AdminAuditLog::SEVERITY_INFO
            );
        }

        return $next($request);
    }

    /**
     * Check if the request has dangerous action confirmation.
     */
    private function hasDangerousActionConfirmation(Request $request): bool
    {
        // Check for confirmation in session (for web requests)
        if ($request->session()->has('dangerous_action_confirmed')) {
            $request->session()->forget('dangerous_action_confirmed');
            return true;
        }

        // Check for confirmation parameter (for API requests)
        return $request->boolean('confirm_dangerous_action', false);
    }

    /**
     * Log dangerous permission attempt.
     */
    private function logDangerousPermissionAttempt(int $userId, array $dangerousPermissions, Request $request): void
    {
        AdminAuditLog::logAction(
            $userId,
            'dangerous_permission_attempt',
            "Attempting to use dangerous permissions: " . implode(', ', $dangerousPermissions),
            null,
            [],
            [],
            [
                'dangerous_permissions' => $dangerousPermissions,
                'endpoint' => $request->url(),
                'method' => $request->method(),
                'request_data' => $request->except(['password', 'password_confirmation', 'token']),
            ],
            AdminAuditLog::SEVERITY_WARNING
        );
    }

    /**
     * Check if the endpoint is sensitive.
     */
    private function isSensitiveEndpoint(Request $request): bool
    {
        $sensitivePatterns = [
            'admin/users/*/delete',
            'admin/users/*/suspend',
            'admin/users/impersonate',
            'admin/tenants/*/delete',
            'admin/system/configuration',
            'admin/bulk-operations',
            'admin/security',
        ];

        $path = $request->path();
        
        foreach ($sensitivePatterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return response requiring dangerous action confirmation.
     */
    private function dangerousActionConfirmationRequired(Request $request, array $dangerousPermissions): BaseResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Dangerous action confirmation required',
                'error' => 'DANGEROUS_ACTION_CONFIRMATION_REQUIRED',
                'dangerous_permissions' => $dangerousPermissions,
                'confirmation_required' => true,
            ], Response::HTTP_PRECONDITION_REQUIRED);
        }

        return response()->view('admin.confirm-dangerous-action', [
            'permissions' => $dangerousPermissions,
            'action' => $request->url(),
            'method' => $request->method(),
        ], Response::HTTP_PRECONDITION_REQUIRED);
    }

    /**
     * Return forbidden response.
     */
    private function forbiddenResponse(Request $request, string $message): BaseResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'INSUFFICIENT_PERMISSIONS',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->view('errors.403', [
            'message' => $message,
        ], Response::HTTP_FORBIDDEN);
    }
}