<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user via API.
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'phone' => 'nullable|string|max:20',
                'country' => 'nullable|string|size:2',
                'gdpr_consent' => 'required|boolean|accepted',
                'marketing_consent' => 'nullable|boolean',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $data = $validator->validated();
            
            // Create the user
            $user = User::create([
                'name' => $data['name'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
                'gdpr_consent' => $data['gdpr_consent'],
                'gdpr_consent_at' => $data['gdpr_consent'] ? now() : null,
                'gdpr_consent_ip' => $request->ip(),
                'marketing_consent' => $data['marketing_consent'] ?? false,
                'marketing_consent_at' => $data['marketing_consent'] ? now() : null,
                'registration_ip' => $request->ip(),
                'registration_user_agent' => $request->userAgent(),
                'is_active' => true,
            ]);
            
            // Create API token
            $token = $user->createToken('API Token')->plainTextToken;
            
            // Fire the registered event
            event(new Registered($user));
            
            // Log the registration
            $user->securityLogs()->create([
                'event' => 'user_registered',
                'description' => 'New user registration via API',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'severity' => 'info',
                'additional_data' => [
                    'gdpr_consent' => $data['gdpr_consent'],
                    'marketing_consent' => $data['marketing_consent'] ?? false,
                ],
            ]);
            
            return response()->json([
                'message' => 'User registered successfully',
                'data' => new UserResource($user),
                'token' => $token,
            ], Response::HTTP_CREATED);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Exception $e) {
            Log::error('API Registration error: ' . $e->getMessage(), [
                'request' => $request->except(['password', 'password_confirmation']),
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred during registration',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Login user via API.
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
                'device_name' => 'nullable|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $credentials = $request->only('email', 'password');
            $user = User::where('email', $credentials['email'])->first();
            
            // Check if user exists
            if (!$user) {
                return response()->json([
                    'message' => 'Invalid credentials',
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Check if user is locked
            if ($user->isLocked()) {
                return response()->json([
                    'message' => 'Account is temporarily locked due to too many failed login attempts',
                    'locked_until' => $user->locked_until,
                ], Response::HTTP_LOCKED);
            }
            
            // Check if user is active
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Account is inactive',
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Verify password
            if (!Hash::check($credentials['password'], $user->password)) {
                $user->incrementFailedLoginAttempts();
                
                return response()->json([
                    'message' => 'Invalid credentials',
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Reset failed attempts and update last login
            $user->resetFailedLoginAttempts();
            $user->update(['last_login_at' => now()]);
            
            // Create API token
            $deviceName = $request->input('device_name', 'API Token');
            $token = $user->createToken($deviceName)->plainTextToken;
            
            // Log the login
            $user->securityLogs()->create([
                'event' => 'user_login',
                'description' => 'User login via API',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'severity' => 'info',
                'additional_data' => [
                    'device_name' => $deviceName,
                ],
            ]);
            
            return response()->json([
                'message' => 'Login successful',
                'data' => new UserResource($user),
                'token' => $token,
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Login error: ' . $e->getMessage(), [
                'request' => $request->except(['password']),
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred during login',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Logout user via API.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke current token
            $request->user()->currentAccessToken()->delete();
            
            // Log the logout
            $user->securityLogs()->create([
                'event' => 'user_logout',
                'description' => 'User logout via API',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'severity' => 'info',
            ]);
            
            return response()->json([
                'message' => 'Logout successful',
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Logout error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred during logout',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Logout from all devices.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke all tokens
            $user->tokens()->delete();
            
            // Log the logout from all devices
            $user->securityLogs()->create([
                'event' => 'user_logout_all',
                'description' => 'User logout from all devices via API',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'severity' => 'info',
            ]);
            
            return response()->json([
                'message' => 'Logout from all devices successful',
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Logout all error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred during logout',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Get the current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->load(['tenants', 'securityLogs' => function ($query) {
                $query->latest()->limit(5);
            }]);
            
            return response()->json([
                'data' => new UserResource($user),
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Me error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while fetching user data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Refresh the current user token.
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke current token
            $request->user()->currentAccessToken()->delete();
            
            // Create new token
            $deviceName = $request->input('device_name', 'API Token');
            $token = $user->createToken($deviceName)->plainTextToken;
            
            // Log the token refresh
            $user->securityLogs()->create([
                'event' => 'token_refreshed',
                'description' => 'API token refreshed',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'severity' => 'info',
            ]);
            
            return response()->json([
                'message' => 'Token refreshed successfully',
                'token' => $token,
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Token refresh error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception' => $e,
            ]);
            
            return response()->json([
                'message' => 'An error occurred while refreshing token',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}