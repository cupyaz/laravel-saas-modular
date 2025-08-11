<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TaxCalculationController;
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

    // Public plan information (no auth required)
    Route::prefix('plans')->group(function () {
        Route::get('/', [PlanController::class, 'index'])
            ->name('api.plans.index');
            
        Route::get('/{plan}', [PlanController::class, 'show'])
            ->name('api.plans.show');
            
        Route::post('/compare', [PlanController::class, 'compare'])
            ->name('api.plans.compare');
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

        // Payment processing
        Route::prefix('payment')->group(function () {
            Route::post('/setup-intent', [PaymentController::class, 'createSetupIntent'])
                ->name('api.payment.setup-intent');
                
            Route::post('/process', [PaymentController::class, 'processPayment'])
                ->name('api.payment.process');
                
            Route::post('/bank-transfer', [PaymentController::class, 'setupBankTransfer'])
                ->name('api.payment.bank-transfer');
                
            Route::get('/methods', [PaymentController::class, 'getPaymentMethods'])
                ->name('api.payment.methods');
                
            Route::delete('/methods/{paymentMethodId}', [PaymentController::class, 'deletePaymentMethod'])
                ->name('api.payment.methods.delete');
        });
        
        // Tax calculation
        Route::post('/calculate-tax', [TaxCalculationController::class, 'calculateTax'])
            ->name('api.calculate-tax');
            
        // Subscription management
        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index'])
                ->name('api.subscriptions.index');
                
            Route::get('/{subscription}', [SubscriptionController::class, 'show'])
                ->name('api.subscriptions.show');
                
            Route::post('/{subscription}/change-plan', [SubscriptionController::class, 'changePlan'])
                ->name('api.subscriptions.change-plan');
                
            Route::post('/{subscription}/pause', [SubscriptionController::class, 'pause'])
                ->name('api.subscriptions.pause');
                
            Route::post('/{subscription}/resume', [SubscriptionController::class, 'resume'])
                ->name('api.subscriptions.resume');
                
            Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancel'])
                ->name('api.subscriptions.cancel');
                
            Route::post('/{subscription}/reactivate', [SubscriptionController::class, 'reactivate'])
                ->name('api.subscriptions.reactivate');
                
            Route::post('/{subscription}/offers/{offer}/accept', [SubscriptionController::class, 'acceptRetentionOffer'])
                ->name('api.subscriptions.offers.accept');
        });

        // Plan recommendations (authenticated)
        Route::get('/plans/recommendations', [PlanController::class, 'recommendations'])
            ->name('api.plans.recommendations');
        
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
                    'payment' => [
                        'POST /api/v1/payment/setup-intent',
                        'POST /api/v1/payment/process',
                        'POST /api/v1/payment/bank-transfer',
                        'GET /api/v1/payment/methods',
                        'DELETE /api/v1/payment/methods/{id}',
                    ],
                    'subscriptions' => [
                        'GET /api/v1/subscriptions',
                        'GET /api/v1/subscriptions/{id}',
                        'POST /api/v1/subscriptions/{id}/change-plan',
                        'POST /api/v1/subscriptions/{id}/pause',
                        'POST /api/v1/subscriptions/{id}/resume',
                        'POST /api/v1/subscriptions/{id}/cancel',
                        'POST /api/v1/subscriptions/{id}/reactivate',
                        'POST /api/v1/subscriptions/{id}/offers/{id}/accept',
                    ],
                    'plans' => [
                        'GET /api/v1/plans',
                        'GET /api/v1/plans/{id}',
                        'POST /api/v1/plans/compare',
                        'GET /api/v1/plans/recommendations',
                    ],
                    'system' => [
                        'GET /api/v1/health',
                        'GET /api/v1/status',
                        'GET /api/v1/info',
                        'POST /api/v1/calculate-tax',
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