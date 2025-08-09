<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Legacy route for backward compatibility
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // Public API routes
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'laravel_version' => app()->version(),
        ]);
    });
    
    Route::get('/status', function () {
        return response()->json([
            'api_status' => 'operational',
            'database_status' => 'operational',
            'cache_status' => 'operational',
            'timestamp' => now(),
            'uptime' => 'Available',
        ]);
    });

    // Authentication routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('api.auth.register');
            
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('api.auth.login');
    });

    // Protected API routes
    Route::middleware('auth:sanctum')->group(function () {
        
        // Authentication management
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('api.auth.logout');
                
            Route::post('/logout-all', [AuthController::class, 'logoutAll'])
                ->name('api.auth.logout-all');
                
            Route::get('/me', [AuthController::class, 'me'])
                ->name('api.auth.me');
                
            Route::post('/refresh', [AuthController::class, 'refresh'])
                ->name('api.auth.refresh');
        });
        
        // User management
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index'])
                ->name('api.users.index');
                
            Route::get('/{user}', [UserController::class, 'show'])
                ->name('api.users.show');
                
            Route::put('/{user}', [UserController::class, 'update'])
                ->name('api.users.update');
                
            Route::delete('/{user}', [UserController::class, 'destroy'])
                ->name('api.users.destroy');
                
            Route::post('/{user}/reactivate', [UserController::class, 'reactivate'])
                ->name('api.users.reactivate');
        });
        
        // Current user profile (convenience routes)
        Route::prefix('profile')->group(function () {
            Route::get('/', [AuthController::class, 'me'])
                ->name('api.profile.show');
                
            Route::put('/', function (Request $request) {
                return app(UserController::class)->update($request, $request->user());
            })->name('api.profile.update');
        });
        
        // API Information and documentation
        Route::get('/info', function () {
            return response()->json([
                'api_version' => '1.0.0',
                'endpoints' => [
                    'authentication' => [
                        'POST /api/v1/auth/register',
                        'POST /api/v1/auth/login',
                        'POST /api/v1/auth/logout',
                        'POST /api/v1/auth/logout-all',
                        'GET /api/v1/auth/me',
                        'POST /api/v1/auth/refresh',
                    ],
                    'users' => [
                        'GET /api/v1/users',
                        'GET /api/v1/users/{id}',
                        'PUT /api/v1/users/{id}',
                        'DELETE /api/v1/users/{id}',
                        'POST /api/v1/users/{id}/reactivate',
                    ],
                    'profile' => [
                        'GET /api/v1/profile',
                        'PUT /api/v1/profile',
                    ],
                    'system' => [
                        'GET /api/v1/health',
                        'GET /api/v1/status',
                        'GET /api/v1/info',
                    ],
                ],
                'rate_limits' => [
                    'register' => '5 requests per minute',
                    'login' => '10 requests per minute',
                    'general' => '60 requests per minute',
                ],
                'authentication' => 'Bearer token (Laravel Sanctum)',
                'documentation' => url('/api/documentation'),
            ]);
        });
    });
});