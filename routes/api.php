<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExampleFeatureController;
use App\Http\Controllers\Api\FeatureController;
use App\Http\Controllers\Api\FreeTierController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TaxCalculationController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UpgradePromptController;
use App\Http\Controllers\Api\UsageController;
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

    // Public feature information (no auth required)
    Route::get('/features', [FeatureController::class, 'allFeatures'])
        ->name('api.features.public');

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

        // Feature access management
        Route::prefix('features')->group(function () {
            Route::get('/', [FeatureController::class, 'index'])
                ->name('api.features.index');
                
            Route::get('/{feature}/check', [FeatureController::class, 'checkAccess'])
                ->name('api.features.check');
                
            Route::post('/{feature}/usage', [FeatureController::class, 'checkUsage'])
                ->name('api.features.usage');
                
            Route::post('/usage/increment', [FeatureController::class, 'incrementUsage'])
                ->name('api.features.increment');
                
            Route::get('/usage/summary', [FeatureController::class, 'usageSummary'])
                ->name('api.features.usage-summary');
                
            Route::get('/recommendations', [FeatureController::class, 'upgradeRecommendations'])
                ->name('api.features.recommendations');
        });

        // Free tier management and feature access
        Route::prefix('free-tier')->group(function () {
            Route::get('/plan', [FreeTierController::class, 'getCurrentPlan'])
                ->name('api.free-tier.plan');
                
            Route::get('/features', [FreeTierController::class, 'getFeatures'])
                ->name('api.free-tier.features');
                
            Route::get('/features/{featureSlug}/check', [FreeTierController::class, 'checkFeatureAccess'])
                ->name('api.free-tier.features.check');
                
            Route::get('/usage', [FreeTierController::class, 'getUsageStats'])
                ->name('api.free-tier.usage');
                
            Route::get('/comparison', [FreeTierController::class, 'getFeatureComparison'])
                ->name('api.free-tier.comparison');
                
            Route::get('/upgrade-recommendations', [FreeTierController::class, 'getUpgradeRecommendations'])
                ->name('api.free-tier.upgrade-recommendations');
        });

        // Usage tracking and analytics
        Route::prefix('usage')->group(function () {
            Route::get('/summary', [UsageController::class, 'summary'])
                ->name('api.usage.summary');
                
            Route::get('/meters', [UsageController::class, 'meters'])
                ->name('api.usage.meters');
                
            Route::get('/analytics', [UsageController::class, 'analytics'])
                ->name('api.usage.analytics');
                
            Route::get('/alerts', [UsageController::class, 'alerts'])
                ->name('api.usage.alerts');
                
            Route::post('/alerts/acknowledge', [UsageController::class, 'acknowledgeAlerts'])
                ->name('api.usage.alerts.acknowledge');
                
            Route::post('/track', [UsageController::class, 'track'])
                ->name('api.usage.track');
                
            Route::post('/can-perform', [UsageController::class, 'canPerform'])
                ->name('api.usage.can-perform');
        });

        // Upgrade prompts and conversion optimization
        Route::prefix('upgrade-prompts')->group(function () {
            Route::get('/', [UpgradePromptController::class, 'getPrompts'])
                ->name('api.upgrade-prompts.get');
                
            Route::post('/action', [UpgradePromptController::class, 'recordAction'])
                ->name('api.upgrade-prompts.action');
                
            Route::get('/recommendations', [UpgradePromptController::class, 'getRecommendations'])
                ->name('api.upgrade-prompts.recommendations');
                
            Route::post('/conversion', [UpgradePromptController::class, 'trackConversion'])
                ->name('api.upgrade-prompts.conversion');
                
            Route::post('/dismiss-all', [UpgradePromptController::class, 'dismissAll'])
                ->name('api.upgrade-prompts.dismiss-all');
                
            Route::get('/assignments', [UpgradePromptController::class, 'getMyAssignments'])
                ->name('api.upgrade-prompts.assignments');
        });

        // Admin-only upgrade prompt analytics and A/B testing
        Route::prefix('admin/upgrade-prompts')->group(function () {
            Route::get('/analytics', [UpgradePromptController::class, 'getAnalytics'])
                ->name('api.admin.upgrade-prompts.analytics');
                
            Route::get('/ab-tests', [UpgradePromptController::class, 'getABTestResults'])
                ->name('api.admin.upgrade-prompts.ab-tests');
                
            Route::post('/ab-tests', [UpgradePromptController::class, 'createABTest'])
                ->name('api.admin.upgrade-prompts.ab-tests.create');
                
            Route::post('/ab-tests/end', [UpgradePromptController::class, 'endABTest'])
                ->name('api.admin.upgrade-prompts.ab-tests.end');
        });

        // Example feature-gated endpoints (demonstrating freemium functionality)
        Route::prefix('examples')->group(function () {
            // Freemium features with usage limits using new FeatureGate middleware
            Route::post('/reports/basic', [ExampleFeatureController::class, 'generateBasicReport'])
                ->middleware('feature.gate:basic_reports,1')
                ->name('api.examples.basic-report');
                
            Route::post('/files/upload', [ExampleFeatureController::class, 'uploadFile'])
                ->middleware('feature.gate:file_storage,1')
                ->name('api.examples.upload-file');
                
            Route::post('/projects', [ExampleFeatureController::class, 'createProject'])
                ->middleware('feature.gate:projects,1')
                ->name('api.examples.create-project');
                
            Route::post('/api-call', [ExampleFeatureController::class, 'makeApiCall'])
                ->middleware('feature.gate:api_access,1')
                ->name('api.examples.api-call');
                
            // Premium-only features
            Route::get('/analytics/advanced', [ExampleFeatureController::class, 'getAdvancedAnalytics'])
                ->middleware('feature.gate:advanced_analytics,1')
                ->name('api.examples.advanced-analytics');
                
            Route::get('/branding', [ExampleFeatureController::class, 'getCustomBranding'])
                ->middleware('feature.gate:custom_branding,1')
                ->name('api.examples.custom-branding');
                
            Route::post('/export', [ExampleFeatureController::class, 'exportData'])
                ->middleware('feature.gate:data_export,1')
                ->name('api.examples.export-data');
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
        
        // Performance monitoring and optimization
        Route::prefix('performance')->group(function () {
            Route::post('/track', [PerformanceController::class, 'track'])
                ->name('api.performance.track');
                
            Route::get('/analytics', [PerformanceController::class, 'analytics'])
                ->name('api.performance.analytics');
                
            Route::get('/recommendations', [PerformanceController::class, 'recommendations'])
                ->name('api.performance.recommendations');
                
            Route::get('/config', [PerformanceController::class, 'config'])
                ->name('api.performance.config');
                
            Route::get('/monitor', [PerformanceController::class, 'monitor'])
                ->name('api.performance.monitor');
        });
        
        // Multi-tenant management and security
        Route::prefix('tenant')->middleware('tenant')->group(function () {
            Route::get('/', [TenantController::class, 'show'])
                ->name('api.tenant.show');
                
            Route::put('/', [TenantController::class, 'update'])
                ->name('api.tenant.update');
                
            Route::get('/security-status', [TenantController::class, 'securityStatus'])
                ->name('api.tenant.security-status');
                
            Route::get('/audit-logs', [TenantController::class, 'auditLogs'])
                ->name('api.tenant.audit-logs');
                
            Route::get('/audit-summary', [TenantController::class, 'auditSummary'])
                ->name('api.tenant.audit-summary');
                
            Route::post('/export-data', [TenantController::class, 'exportData'])
                ->name('api.tenant.export-data');
                
            Route::post('/rotate-encryption-key', [TenantController::class, 'rotateEncryptionKey'])
                ->name('api.tenant.rotate-encryption-key');
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