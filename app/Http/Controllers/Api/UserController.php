<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|max:255',
                'sort_by' => 'string|in:name,email,created_at,last_login_at',
                'sort_direction' => 'string|in:asc,desc',
                'is_active' => 'boolean',
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
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
            }
            
            // Apply active status filter
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            
            // Apply sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);
            
            // Paginate results
            $perPage = $request->input('per_page', 15);
            $users = $query->with(['tenants', 'securityLogs' => function ($query) {
                $query->latest()->limit(5); // Only load recent security logs
            }])->paginate($perPage);
            
            return response()->json([
                'data' => UserResource::collection($users->items()),
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
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage(), [
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
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        try {
            // Load relationships
            $user->load(['tenants', 'securityLogs' => function ($query) {
                $query->latest()->limit(10);
            }]);
            
            return response()->json([
                'data' => new UserResource($user),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while fetching the user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Update the specified user.
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
                'date_of_birth' => 'sometimes|nullable|date|before:today',
                'gender' => 'sometimes|nullable|in:male,female,other,prefer_not_to_say',
                'company' => 'sometimes|nullable|string|max:255',
                'job_title' => 'sometimes|nullable|string|max:255',
                'bio' => 'sometimes|nullable|string|max:1000',
                'country' => 'sometimes|nullable|string|size:2',
                'timezone' => 'sometimes|nullable|string|max:50',
                'is_active' => 'sometimes|boolean',
                'preferences' => 'sometimes|array',
                'marketing_consent' => 'sometimes|boolean',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $data = $validator->validated();
            
            // Handle marketing consent timestamp
            if (isset($data['marketing_consent'])) {
                $data['marketing_consent_at'] = $data['marketing_consent'] ? now() : null;
            }
            
            $user->update($data);
            
            // Log the update
            $user->securityLogs()->create([
                'event' => 'user_updated',
                'description' => 'User profile updated via API',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'severity' => 'info',
                'additional_data' => [
                    'updated_fields' => array_keys($data),
                    'updated_by' => $request->user()->id,
                ],
            ]);
            
            // Refresh the user with relationships
            $user->load(['tenants', 'securityLogs' => function ($query) {
                $query->latest()->limit(5);
            }]);
            
            return response()->json([
                'message' => 'User updated successfully',
                'data' => new UserResource($user),
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
     * Remove the specified user from storage (soft delete).
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            // Prevent self-deletion
            if (auth()->user()->id === $user->id) {
                return response()->json([
                    'message' => 'You cannot delete your own account via API',
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Deactivate instead of delete to maintain data integrity
            $user->update(['is_active' => false]);
            
            // Log the deactivation
            $user->securityLogs()->create([
                'event' => 'user_deactivated',
                'description' => 'User account deactivated via API',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'severity' => 'warning',
                'additional_data' => [
                    'deactivated_by' => auth()->user()->id,
                ],
            ]);
            
            return response()->json([
                'message' => 'User account deactivated successfully',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error deactivating user: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while deactivating the user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Reactivate a deactivated user.
     */
    public function reactivate(User $user): JsonResponse
    {
        try {
            $user->update(['is_active' => true]);
            
            // Log the reactivation
            $user->securityLogs()->create([
                'event' => 'user_reactivated',
                'description' => 'User account reactivated via API',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'severity' => 'info',
                'additional_data' => [
                    'reactivated_by' => auth()->user()->id,
                ],
            ]);
            
            return response()->json([
                'message' => 'User account reactivated successfully',
                'data' => new UserResource($user),
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
}