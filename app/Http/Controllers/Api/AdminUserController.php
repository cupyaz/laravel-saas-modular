<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\AdminAuditLog;
use App\Models\AdminBulkOperation;
use App\Models\AdminRole;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserImpersonationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Services\AdminNotificationService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function __construct(
        protected AdminNotificationService $notificationService
    ) {}

    /**
     * Display a paginated listing of users with advanced filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|max:255',
                'sort_by' => 'string|in:name,email,created_at,last_login_at,last_admin_login_at',
                'sort_direction' => 'string|in:asc,desc',
                'role' => 'string|in:user,admin,super_admin',
                'is_active' => 'boolean',
                'is_suspended' => 'boolean',
                'has_admin_role' => 'boolean',
                'tenant_id' => 'integer|exists:tenants,id',
                'admin_role_id' => 'integer|exists:admin_roles,id',
                'created_from' => 'date',
                'created_to' => 'date',
                'last_login_from' => 'date',
                'last_login_to' => 'date',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $query = User::query();
            
            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('company', 'like', "%{$search}%")
                      ->orWhere('job_title', 'like', "%{$search}%");
                });
            }
            
            // Apply role filter
            if ($request->filled('role')) {
                $query->where('role', $request->input('role'));
            }
            
            // Apply active status filter
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            
            // Apply suspension filter
            if ($request->has('is_suspended')) {
                if ($request->boolean('is_suspended')) {
                    $query->whereNotNull('suspended_at');
                } else {
                    $query->whereNull('suspended_at');
                }
            }
            
            // Apply admin role filter
            if ($request->has('has_admin_role')) {
                if ($request->boolean('has_admin_role')) {
                    $query->admins();
                } else {
                    $query->where('is_super_admin', false)
                          ->whereDoesntHave('activeAdminRoles');
                }
            }
            
            // Apply tenant filter
            if ($request->filled('tenant_id')) {
                $query->whereHas('tenants', function ($q) use ($request) {
                    $q->where('tenant_id', $request->input('tenant_id'));
                });
            }
            
            // Apply specific admin role filter
            if ($request->filled('admin_role_id')) {
                $query->whereHas('activeAdminRoles', function ($q) use ($request) {
                    $q->where('admin_role_id', $request->input('admin_role_id'));
                });
            }
            
            // Apply date range filters
            if ($request->filled('created_from')) {
                $query->whereDate('created_at', '>=', $request->input('created_from'));
            }
            
            if ($request->filled('created_to')) {
                $query->whereDate('created_at', '<=', $request->input('created_to'));
            }
            
            if ($request->filled('last_login_from')) {
                $query->whereDate('last_login_at', '>=', $request->input('last_login_from'));
            }
            
            if ($request->filled('last_login_to')) {
                $query->whereDate('last_login_at', '<=', $request->input('last_login_to'));
            }
            
            // Apply sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);
            
            // Paginate results
            $perPage = $request->input('per_page', 15);
            $users = $query->with([
                'tenants',
                'activeAdminRoles',
                'securityLogs' => function ($query) {
                    $query->latest()->limit(3);
                },
                'targetedAuditLogs' => function ($query) {
                    $query->latest()->limit(3);
                }
            ])->paginate($perPage);
            
            // Get summary statistics
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $suspendedUsers = User::whereNotNull('suspended_at')->count();
            $adminUsers = User::admins()->count();
            $newUsersThisMonth = User::whereMonth('created_at', now()->month)
                                   ->whereYear('created_at', now()->year)
                                   ->count();
            
            return response()->json([
                'data' => AdminUserResource::collection($users->items()),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'links' => [
                    'first' => $users->url(1),
                    'last' => $users->url($users->lastPage()),
                    'prev' => $users->previousPageUrl(),
                    'next' => $users->nextPageUrl(),
                ],
                'summary' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'suspended_users' => $suspendedUsers,
                    'admin_users' => $adminUsers,
                    'new_users_this_month' => $newUsersThisMonth,
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching admin users: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while fetching users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Display the specified user with detailed admin information.
     */
    public function show(User $user): JsonResponse
    {
        try {
            // Load comprehensive relationships for admin view
            $user->load([
                'tenants' => function ($query) {
                    $query->with('subscription');
                },
                'activeAdminRoles' => function ($query) {
                    $query->with('users');
                },
                'adminRoles' => function ($query) {
                    $query->withPivot(['assigned_by', 'assigned_at', 'expires_at']);
                },
                'securityLogs' => function ($query) {
                    $query->latest()->limit(20);
                },
                'targetedAuditLogs' => function ($query) {
                    $query->with('adminUser')->latest()->limit(20);
                },
                'adminAuditLogs' => function ($query) {
                    $query->latest()->limit(10);
                }
            ]);
            
            // Get impersonation history for this user
            $impersonationHistory = UserImpersonationSession::byImpersonatedUser($user->id)
                ->with('adminUser')
                ->latest('started_at')
                ->limit(10)
                ->get();
            
            return response()->json([
                'data' => new AdminUserResource($user),
                'impersonation_history' => $impersonationHistory,
                'permissions' => $user->getAllAdminPermissions(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching admin user details: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while fetching user details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Create a new user (admin function).
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'phone' => 'sometimes|nullable|string|max:20',
                'company' => 'sometimes|nullable|string|max:255',
                'job_title' => 'sometimes|nullable|string|max:255',
                'country' => 'sometimes|nullable|string|size:2',
                'timezone' => 'sometimes|nullable|string|max:50',
                'role' => 'sometimes|string|in:user,admin',
                'is_active' => 'sometimes|boolean',
                'admin_notes' => 'sometimes|nullable|string|max:1000',
                'requires_2fa' => 'sometimes|boolean',
                'send_welcome_email' => 'sometimes|boolean',
                'admin_role_ids' => 'sometimes|array',
                'admin_role_ids.*' => 'integer|exists:admin_roles,id',
                'tenant_id' => 'sometimes|integer|exists:tenants,id',
                'tenant_role' => 'sometimes|string|in:owner,admin,member',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            DB::beginTransaction();
            
            $userData = $validator->validated();
            $userData['password'] = Hash::make($userData['password']);
            $userData['email_verified_at'] = now(); // Admin-created users are pre-verified
            
            // Remove non-user fields
            $adminRoleIds = $userData['admin_role_ids'] ?? [];
            $tenantId = $userData['tenant_id'] ?? null;
            $tenantRole = $userData['tenant_role'] ?? 'member';
            $sendWelcomeEmail = $userData['send_welcome_email'] ?? true;
            
            unset($userData['admin_role_ids'], $userData['tenant_id'], $userData['tenant_role'], 
                  $userData['send_welcome_email'], $userData['password_confirmation']);
            
            $user = User::create($userData);
            
            // Assign admin roles if provided
            if (!empty($adminRoleIds)) {
                foreach ($adminRoleIds as $roleId) {
                    $role = AdminRole::find($roleId);
                    if ($role) {
                        $user->assignAdminRole($role, $request->user()->id);
                    }
                }
            }
            
            // Associate with tenant if provided
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    $user->tenants()->attach($tenantId, ['role' => $tenantRole]);
                }
            }
            
            // Send welcome email if requested
            if ($sendWelcomeEmail) {
                $this->notificationService->notifyWelcomeUser(
                    $user,
                    null,
                    $request->user()
                );            
            }
            
            // Log user creation
            AdminAuditLog::logUserCreated($request->user()->id, $user);
            
            DB::commit();
            
            // Refresh user with relationships
            $user->load([
                'tenants',
                'activeAdminRoles',
                'securityLogs' => function ($query) {
                    $query->latest()->limit(5);
                }
            ]);
            
            return response()->json([
                'message' => 'User created successfully',
                'data' => new AdminUserResource($user),
            ], Response::HTTP_CREATED);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating user: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while creating the user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Update the specified user (admin function).
     */
    public function update(Request $request, User $user): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'phone' => 'sometimes|nullable|string|max:20',
                'company' => 'sometimes|nullable|string|max:255',
                'job_title' => 'sometimes|nullable|string|max:255',
                'country' => 'sometimes|nullable|string|size:2',
                'timezone' => 'sometimes|nullable|string|max:50',
                'role' => 'sometimes|string|in:user,admin',
                'is_active' => 'sometimes|boolean',
                'admin_notes' => 'sometimes|nullable|string|max:1000',
                'requires_2fa' => 'sometimes|boolean',
                'password_expires_at' => 'sometimes|nullable|date|after:now',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $oldValues = $user->toArray();
            $newValues = $validator->validated();
            
            $user->update($newValues);
            
            // Log the update
            AdminAuditLog::logUserUpdated(
                $request->user()->id,
                $user,
                array_intersect_key($oldValues, $newValues),
                $newValues
            );
            
            // Refresh the user with relationships
            $user->load([
                'tenants',
                'activeAdminRoles',
                'securityLogs' => function ($query) {
                    $query->latest()->limit(5);
                }
            ]);
            
            return response()->json([
                'message' => 'User updated successfully',
                'data' => new AdminUserResource($user),
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'request' => $request->all(),
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while updating the user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Suspend a user account.
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
                'notify_user' => 'sometimes|boolean',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Prevent self-suspension
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'message' => 'You cannot suspend your own account',
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Prevent suspending super admins (unless done by another super admin)
            if ($user->is_super_admin && !$request->user()->is_super_admin) {
                return response()->json([
                    'message' => 'Only super administrators can suspend other super administrators',
                ], Response::HTTP_FORBIDDEN);
            }
            
            $reason = $request->input('reason');
            $notifyUser = $request->boolean('notify_user', true);
            
            $user->suspend($reason, $request->user()->id);
            
            // Send notification email if requested
            if ($notifyUser) {
                $this->notificationService->notifyUserSuspended(
                    $user,
                    $reason,
                    $request->user(),
                    false // Not permanent
                );
            }
            // Log the suspension
            AdminAuditLog::logUserSuspended($request->user()->id, $user, $reason);
            
            return response()->json([
                'message' => 'User account suspended successfully',
                'data' => new AdminUserResource($user),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error suspending user: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'request' => $request->all(),
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while suspending the user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Reactivate a suspended user account.
     */
    public function reactivate(Request $request, User $user): JsonResponse
    {
        try {
            if (!$user->isSuspended()) {
                return response()->json([
                    'message' => 'User account is not suspended',
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $user->reactivate();

            // Send reactivation notification
            $this->notificationService->notifyUserReactivated($user, $request->user());            
            // Log the reactivation
            AdminAuditLog::logAction(
                $request->user()->id,
                AdminAuditLog::ACTION_USER_REACTIVATED,
                "User '{$user->email}' reactivated by admin",
                $user,
                ['suspended_at' => $user->suspended_at, 'is_active' => false],
                ['suspended_at' => null, 'is_active' => true],
                ['user_id' => $user->id],
                AdminAuditLog::SEVERITY_INFO
            );
            
            return response()->json([
                'message' => 'User account reactivated successfully',
                'data' => new AdminUserResource($user),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error reactivating user: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while reactivating the user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Start impersonating a user.
     */
    public function startImpersonation(Request $request, User $user): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
                'duration_hours' => 'sometimes|integer|min:1|max:8',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Prevent self-impersonation
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'message' => 'You cannot impersonate yourself',
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Prevent impersonating super admins
            if ($user->is_super_admin) {
                return response()->json([
                    'message' => 'Super administrators cannot be impersonated',
                ], Response::HTTP_FORBIDDEN);
            }
            
            $reason = $request->input('reason');
            $sessionId = Str::uuid()->toString();
            
            // Start impersonation session
            $impersonationSession = UserImpersonationSession::start(
                $request->user()->id,
                $user->id,
                $sessionId,
                $reason
            );
            
            // Log the impersonation
            AdminAuditLog::logUserImpersonated($request->user()->id, $user, $reason);
            
            return response()->json([
                'message' => 'User impersonation started successfully',
                'session_id' => $sessionId,
                'impersonated_user' => new AdminUserResource($user),
                'expires_at' => $impersonationSession->started_at->addHours(4),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error starting user impersonation: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'admin_user_id' => $request->user()->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while starting impersonation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * End impersonation session.
     */
    public function endImpersonation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $session = UserImpersonationSession::findActiveBySessionId($request->input('session_id'));
            
            if (!$session) {
                return response()->json([
                    'message' => 'Impersonation session not found or already ended',
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verify the admin user matches
            if ($session->admin_user_id !== $request->user()->id) {
                return response()->json([
                    'message' => 'You can only end your own impersonation sessions',
                ], Response::HTTP_FORBIDDEN);
            }
            
            $session->end();
            
            return response()->json([
                'message' => 'Impersonation session ended successfully',
                'duration' => $session->duration,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error ending user impersonation: ' . $e->getMessage(), [
                'admin_user_id' => $request->user()->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while ending impersonation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Reset user password (admin function).
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'new_password' => 'sometimes|string|min:8|confirmed',
                'force_change' => 'sometimes|boolean',
                'send_email' => 'sometimes|boolean',
                'generate_password' => 'sometimes|boolean',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $generatePassword = $request->boolean('generate_password', false);
            $forceChange = $request->boolean('force_change', true);
            $sendEmail = $request->boolean('send_email', true);
            
            if ($generatePassword) {
                $newPassword = Str::random(12);
            } else {
                $newPassword = $request->input('new_password');
                if (!$newPassword) {
                    return response()->json([
                        'message' => 'Either provide new_password or set generate_password to true',
                    ], Response::HTTP_BAD_REQUEST);
                }
            }
            
            // Update password
            $user->update([
                'password' => Hash::make($newPassword),
                'password_expires_at' => $forceChange ? now()->addDays(7) : null,
            ]);
            
            // Send password reset email if requested
            if ($sendEmail) {
                $this->notificationService->notifyPasswordResetByAdmin(
                    $user,
                    $request->user(),
                    $generatePassword ? $newPassword : null,
                    $forceChange
                );            
            }
            
            // Log the password reset
            AdminAuditLog::logAction(
                $request->user()->id,
                AdminAuditLog::ACTION_USER_PASSWORD_RESET,
                "Password reset for user '{$user->email}' by admin",
                $user,
                [],
                [],
                [
                    'user_id' => $user->id,
                    'force_change' => $forceChange,
                    'password_generated' => $generatePassword,
                ],
                AdminAuditLog::SEVERITY_INFO
            );
            
            $response = [
                'message' => 'User password reset successfully',
                'force_change' => $forceChange,
            ];
            
            // Only include password in response if it was generated
            if ($generatePassword) {
                $response['generated_password'] = $newPassword;
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('Error resetting user password: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'admin_user_id' => $request->user()->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while resetting the password',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}