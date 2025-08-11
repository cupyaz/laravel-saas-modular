<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\SetupIntent;
use Stripe\Stripe;

class PaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Calculate tax for a specific plan and location.
     */
    public function calculateTax(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'country_code' => 'required|string|size:2',
            'state_code' => 'nullable|string|max:10',
            'postal_code' => 'nullable|string|max:20',
        ]);

        try {
            $plan = Plan::findOrFail($request->plan_id);
            $taxData = $this->calculateTaxForLocation(
                $plan->price,
                $request->country_code,
                $request->state_code,
                $request->postal_code
            );

            return response()->json([
                'subtotal' => $plan->price,
                'tax' => $taxData,
                'total' => $plan->price + $taxData['amount'],
            ]);

        } catch (\Exception $e) {
            Log::error('Tax calculation failed', [
                'error' => $e->getMessage(),
                'plan_id' => $request->plan_id,
                'country' => $request->country_code,
            ]);

            return response()->json([
                'message' => 'Tax calculation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a setup intent for storing payment methods.
     */
    public function createSetupIntent(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $user->tenant ?? Tenant::create([
                'user_id' => $user->id,
                'name' => $user->name . "'s Tenant",
            ]);

            // Ensure user has Stripe customer
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            $setupIntent = SetupIntent::create([
                'customer' => $user->stripe_id,
                'usage' => 'off_session',
            ]);

            return response()->json([
                'client_secret' => $setupIntent->client_secret,
            ]);

        } catch (\Exception $e) {
            Log::error('Setup intent creation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Failed to create setup intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process subscription payment.
     */
    public function processPayment(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method_id' => 'required|string',
            'country_code' => 'required|string|size:2',
            'state_code' => 'nullable|string|max:10',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'required|string|max:100',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();
        
        try {
            $user = Auth::user();
            $plan = Plan::findOrFail($request->plan_id);

            // Ensure user has Stripe customer
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            // Create or update tenant
            $tenant = $user->tenant ?? Tenant::create([
                'user_id' => $user->id,
                'name' => $user->name . "'s Organization",
            ]);

            // Update tenant billing information
            $tenant->update([
                'billing_address' => [
                    'country' => $request->country_code,
                    'state' => $request->state_code,
                    'postal_code' => $request->postal_code,
                    'city' => $request->city,
                    'line1' => $request->address_line1,
                    'line2' => $request->address_line2,
                ],
                'tax_id' => $request->tax_id,
            ]);

            // Attach payment method to customer
            $user->addPaymentMethod($request->payment_method_id);
            $user->updateDefaultPaymentMethod($request->payment_method_id);

            // Calculate tax
            $taxData = $this->calculateTaxForLocation(
                $plan->price,
                $request->country_code,
                $request->state_code,
                $request->postal_code
            );

            // Create subscription
            $subscriptionBuilder = $user->newSubscription('default', $plan->stripe_price_id)
                ->trialDays($plan->trial_days ?? 0);

            // Add tax if applicable
            if ($taxData['amount'] > 0) {
                $subscriptionBuilder->withTax($taxData['rate']);
            }

            $stripeSubscription = $subscriptionBuilder->create($request->payment_method_id);

            // Create our internal subscription record
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'stripe_subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status,
                'internal_status' => 'active',
                'current_period_start' => now()->createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => now()->createFromTimestamp($stripeSubscription->current_period_end),
                'trial_ends_at' => $stripeSubscription->trial_end ? 
                    now()->createFromTimestamp($stripeSubscription->trial_end) : null,
            ]);

            DB::commit();

            Log::info('Subscription created successfully', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
            ]);

            return response()->json([
                'message' => 'Subscription created successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'trial_ends_at' => $subscription->trial_ends_at,
                    'current_period_end' => $subscription->current_period_end,
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'plan_id' => $request->plan_id,
            ]);

            // Handle specific Stripe errors
            if ($e instanceof \Stripe\Exception\CardException) {
                return response()->json([
                    'message' => 'Payment failed',
                    'error' => $e->getMessage(),
                    'type' => 'card_error',
                ], 402);
            }

            if ($e instanceof \Stripe\Exception\InvalidRequestException) {
                return response()->json([
                    'message' => 'Invalid payment request',
                    'error' => $e->getMessage(),
                ], 400);
            }

            return response()->json([
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Setup bank transfer payment.
     */
    public function setupBankTransfer(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'country_code' => 'required|string|size:2',
            'city' => 'required|string|max:100',
            'address_line1' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        
        try {
            $user = Auth::user();
            $plan = Plan::findOrFail($request->plan_id);

            // Create or update tenant
            $tenant = $user->tenant ?? Tenant::create([
                'user_id' => $user->id,
                'name' => $user->name . "'s Organization",
            ]);

            // Update tenant billing information
            $tenant->update([
                'billing_address' => [
                    'country' => $request->country_code,
                    'city' => $request->city,
                    'line1' => $request->address_line1,
                ],
            ]);

            // Create pending subscription
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'internal_status' => 'active',
                'metadata' => [
                    'payment_method' => 'bank_transfer',
                    'requires_manual_activation' => true,
                ],
            ]);

            DB::commit();

            Log::info('Bank transfer subscription created', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Bank transfer setup completed',
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                ],
                'bank_details' => [
                    'account_name' => config('app.name'),
                    'account_number' => config('payment.bank.account_number', 'XXXX-XXXX-XXXX'),
                    'routing_number' => config('payment.bank.routing_number', 'XXXXXXX'),
                    'swift_code' => config('payment.bank.swift_code', 'XXXXXXX'),
                    'reference' => 'SUB-' . $subscription->id,
                    'amount' => $plan->getFormattedPriceAttribute(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bank transfer setup failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'plan_id' => $request->plan_id,
            ]);

            return response()->json([
                'message' => 'Bank transfer setup failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate tax for a specific location.
     */
    protected function calculateTaxForLocation(float $amount, string $country, ?string $state = null, ?string $postalCode = null): array
    {
        // EU VAT rates (simplified)
        $euVatRates = [
            'AT' => 20.0, 'BE' => 21.0, 'BG' => 20.0, 'CY' => 19.0, 'CZ' => 21.0,
            'DE' => 19.0, 'DK' => 25.0, 'EE' => 20.0, 'ES' => 21.0, 'FI' => 24.0,
            'FR' => 20.0, 'GR' => 24.0, 'HR' => 25.0, 'HU' => 27.0, 'IE' => 23.0,
            'IT' => 22.0, 'LT' => 21.0, 'LU' => 17.0, 'LV' => 21.0, 'MT' => 18.0,
            'NL' => 21.0, 'PL' => 23.0, 'PT' => 23.0, 'RO' => 19.0, 'SE' => 25.0,
            'SI' => 22.0, 'SK' => 20.0,
        ];

        // US state tax rates (simplified)
        $usStateTaxRates = [
            'CA' => 8.25, 'NY' => 8.0, 'TX' => 6.25, 'FL' => 6.0, 'WA' => 6.5,
            'OR' => 0.0, 'MT' => 0.0, 'NH' => 0.0, 'DE' => 0.0,
        ];

        $taxRate = 0.0;
        $taxType = 'none';

        if ($country === 'US' && $state && isset($usStateTaxRates[$state])) {
            $taxRate = $usStateTaxRates[$state];
            $taxType = 'sales_tax';
        } elseif (isset($euVatRates[$country])) {
            $taxRate = $euVatRates[$country];
            $taxType = 'vat';
        }

        $taxAmount = ($amount * $taxRate) / 100;

        return [
            'type' => $taxType,
            'rate' => $taxRate,
            'amount' => round($taxAmount, 2),
            'formatted_amount' => '$' . number_format($taxAmount, 2),
            'description' => $taxType === 'vat' ? "VAT ({$taxRate}%)" : 
                           ($taxType === 'sales_tax' ? "Sales Tax ({$taxRate}%)" : 'No Tax'),
        ];
    }

    /**
     * Get current user's payment methods.
     */
    public function getPaymentMethods(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasStripeId()) {
                return response()->json(['payment_methods' => []]);
            }

            $paymentMethods = $user->paymentMethods()->map(function ($paymentMethod) {
                return [
                    'id' => $paymentMethod->id,
                    'type' => $paymentMethod->type,
                    'card' => $paymentMethod->card ? [
                        'brand' => $paymentMethod->card->brand,
                        'last4' => $paymentMethod->card->last4,
                        'exp_month' => $paymentMethod->card->exp_month,
                        'exp_year' => $paymentMethod->card->exp_year,
                    ] : null,
                    'is_default' => $paymentMethod->id === $user->defaultPaymentMethod()?->id,
                ];
            });

            return response()->json(['payment_methods' => $paymentMethods]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment methods', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a payment method.
     */
    public function deletePaymentMethod(Request $request, string $paymentMethodId): JsonResponse
    {
        try {
            $user = Auth::user();
            $paymentMethod = $user->findPaymentMethod($paymentMethodId);

            if (!$paymentMethod) {
                return response()->json([
                    'message' => 'Payment method not found'
                ], 404);
            }

            $paymentMethod->delete();

            return response()->json([
                'message' => 'Payment method deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete payment method', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'payment_method_id' => $paymentMethodId,
            ]);

            return response()->json([
                'message' => 'Failed to delete payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}