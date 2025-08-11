<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    // Registration routes with rate limiting
    Route::get('/register', [RegisterController::class, 'create'])
        ->name('register');
    
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,60') // 5 attempts per minute
        ->name('register.store');
});

// Email verification routes
Route::middleware('auth')->group(function () {
    // Email verification notice
    Route::get('/email/verify', [RegisterController::class, 'showVerificationNotice'])
        ->name('verification.notice');
    
    // Email verification handler
    Route::get('/email/verify/{id}/{hash}', [RegisterController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1']) // 6 attempts per minute
        ->name('verification.verify');
    
    // Resend verification email
    Route::post('/email/verification-notification', [RegisterController::class, 'resendVerification'])
        ->middleware('throttle:3,60') // 3 attempts per hour
        ->name('verification.resend');
});

// Onboarding routes - authenticated and verified users only
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'start'])
        ->name('onboarding.start');
    
    Route::get('/onboarding/profile', [OnboardingController::class, 'profile'])
        ->name('onboarding.profile');
    
    Route::get('/onboarding/preferences', [OnboardingController::class, 'preferences'])
        ->name('onboarding.preferences');
    
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])
        ->name('onboarding.complete');
    
    Route::post('/onboarding/skip', [OnboardingController::class, 'skip'])
        ->name('onboarding.skip');
});

// Protected dashboard routes
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

// Payment routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/payment/checkout/{planId}', [PaymentController::class, 'checkout'])
        ->name('payment.checkout');
        
    Route::get('/payment/success', [PaymentController::class, 'success'])
        ->name('payment.success');
        
    Route::get('/payment/cancel', [PaymentController::class, 'cancel'])
        ->name('payment.cancel');
        
    Route::get('/payment/bank-transfer-instructions', [PaymentController::class, 'bankTransferInstructions'])
        ->name('payment.bank-transfer-instructions');
        
    Route::get('/billing', [PaymentController::class, 'billing'])
        ->name('billing.dashboard');
});

// Stripe webhook routes (no auth required)
Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

// Health check route
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => config('app.version', '1.0.0'),
    ]);
});