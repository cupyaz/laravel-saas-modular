<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Note: defaultStringLength is not needed in Laravel 11
        // Schema::defaultStringLength(191);
        
        $this->configureRateLimiting();
    }
    
    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // API General Rate Limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Registration Rate Limiting (more restrictive)
        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        // Login Rate Limiting
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perHour(30)->by($request->ip()),
            ];
        });

        // Global API Rate Limiting (fallback)
        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        // Authenticated user rate limiting (higher limits)
        RateLimiter::for('authenticated', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });
    }
}