<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Display the payment checkout page.
     */
    public function checkout($planId): View
    {
        // Mock plan data per il test
        $plan = (object) [
            'id' => $planId,
            'name' => 'Premium Plan',
            'description' => 'Full access to all features',
            'price' => 29.99,
            'billing_period' => 'monthly',
            'trial_days' => 14,
            'features' => ['unlimited_users', 'priority_support', 'advanced_analytics']
        ];

        return view('payment.checkout', [
            'plan' => $plan,
            'user' => auth()->user(),
        ]);
    }

    /**
     * Display the payment success page.
     */
    public function success(Request $request): View
    {
        return view('payment.success', [
            'subscription' => null, // Mock per ora
            'user' => auth()->user(),
        ]);
    }

    /**
     * Display the payment cancellation page.
     */
    public function cancel(): View
    {
        return view('payment.cancel', [
            'user' => auth()->user(),
        ]);
    }

    /**
     * Display bank transfer instructions.
     */
    public function bankTransferInstructions(): View
    {
        return view('payment.bank-transfer-instructions', [
            'user' => auth()->user(),
        ]);
    }

    /**
     * Display the billing dashboard.
     */
    public function billing(): View
    {
        return view('billing.dashboard', [
            'user' => auth()->user(),
            'tenant' => null,
            'subscriptions' => collect([]),
            'invoices' => collect([]),
            'paymentMethods' => collect([]),
        ]);
    }
}