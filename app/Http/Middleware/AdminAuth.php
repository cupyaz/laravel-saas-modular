<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): BaseResponse
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return $this->unauthorizedResponse($request, 'Authentication required');
        }

        $user = $request->user();

        // Check if user is active
        if (!$user->is_active) {
            AdminAuditLog::logFailedAdminLogin($user->email, 'User account is inactive');
            return $this->forbiddenResponse($request, 'User account is inactive');
        }

        // Check if user is suspended
        if ($user->isSuspended()) {
            AdminAuditLog::logFailedAdminLogin($user->email, 'User account is suspended');
            return $this->forbiddenResponse($request, 'User account is suspended');
        }

        // Check if user is an admin
        if (!$user->isAdmin()) {
            AdminAuditLog::logFailedAdminLogin($user->email, 'User does not have admin privileges');
            return $this->forbiddenResponse($request, 'Insufficient privileges');
        }

        // Check if password needs to be changed
        if ($user->needsPasswordChange()) {
            return $this->passwordChangeRequiredResponse($request);
        }

        // Check specific permissions if provided
        if (!empty($permissions)) {
            $hasRequiredPermission = false;
            
            foreach ($permissions as $permission) {
                if ($user->hasAdminPermission($permission)) {
                    $hasRequiredPermission = true;
                    break;
                }
            }

            if (!$hasRequiredPermission) {
                AdminAuditLog::logAction(
                    $user->id,
                    AdminAuditLog::ACTION_FAILED_LOGIN,
                    "Access denied - missing required permissions: " . implode(', ', $permissions),
                    null,
                    [],
                    [],
                    ['required_permissions' => $permissions],
                    AdminAuditLog::SEVERITY_WARNING
                );

                return $this->forbiddenResponse(
                    $request, 
                    'Missing required permissions: ' . implode(', ', $permissions)
                );
            }
        }

        // Update last admin login time
        $user->update(['last_admin_login_at' => now()]);

        // Log successful admin access if this is the first request in the session
        if (!$request->session()->get('admin_session_logged', false)) {
            AdminAuditLog::logAdminLogin($user->id);
            $request->session()->put('admin_session_logged', true);
        }

        return $next($request);
    }

    /**
     * Return unauthorized response.
     */
    private function unauthorizedResponse(Request $request, string $message): BaseResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'UNAUTHORIZED',
                'redirect' => route('login'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login')->with('error', $message);
    }

    /**
     * Return forbidden response.
     */
    private function forbiddenResponse(Request $request, string $message): BaseResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'FORBIDDEN',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->view('errors.403', ['message' => $message], Response::HTTP_FORBIDDEN);
    }

    /**
     * Return password change required response.
     */
    private function passwordChangeRequiredResponse(Request $request): BaseResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password change required',
                'error' => 'PASSWORD_CHANGE_REQUIRED',
                'redirect' => route('admin.password.change'),
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('admin.password.change')
            ->with('warning', 'You must change your password before continuing.');
    }
}