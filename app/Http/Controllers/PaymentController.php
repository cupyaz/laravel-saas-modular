<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Display the payment checkout page.
     */
    public function checkout($planId): View
    {
        $plan = Plan::findOrFail($planId);

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
        $user = auth()->user();
        $tenant = $user->tenant;
        $subscriptions = collect([]);
        $invoices = collect([]);
        $paymentMethods = collect([]);

        if ($tenant) {
            $subscriptions = $tenant->subscriptions()->with('plan')->get();
        }

        if ($user->hasStripeId()) {
            try {
                $paymentMethods = $user->paymentMethods();
            } catch (\Exception $e) {
                // Handle Stripe errors gracefully
                $paymentMethods = collect([]);
            }
        }

        return view('billing.dashboard', [
            'user' => $user,
            'tenant' => $tenant,
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'paymentMethods' => $paymentMethods,
        ]);
    }
}