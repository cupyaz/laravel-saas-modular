<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->web(prepend: [
            \App\Http\Middleware\MobileOptimization::class,
            \App\Http\Middleware\TenantIsolation::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\TenantIsolation::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'feature' => \App\Http\Middleware\CheckFeatureAccess::class,
            'feature.gate' => \App\Http\Middleware\FeatureGate::class,
            'mobile' => \App\Http\Middleware\MobileOptimization::class,
            'tenant' => \App\Http\Middleware\TenantIsolation::class,
            'api.version' => \App\Http\Middleware\ApiVersioning::class,
            'api.rate' => \App\Http\Middleware\ApiRateLimit::class,
            'admin' => \App\Http\Middleware\AdminAuth::class,
            'admin_permission' => \App\Http\Middleware\AdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();