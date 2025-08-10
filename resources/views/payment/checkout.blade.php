@extends('layouts.app')

@section('title', 'Secure Payment - ' . config('app.name'))

@section('head')
<script src="https://js.stripe.com/v3/"></script>
<meta name="viewport" content="width=device-width, initial-scale=1">
@endsection

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Secure Payment</h1>
            <p class="text-gray-600">Complete your subscription to {{ config('app.name') }}</p>
        </div>

        <div class="lg:grid lg:grid-cols-2 lg:gap-8">
            <!-- Payment Form -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow-lg rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Payment Information</h2>
                        <p class="text-sm text-gray-600">Your payment is secured with 256-bit SSL encryption</p>
                    </div>
                    
                    <form id="payment-form" class="p-6 space-y-6">
                        @csrf
                        <input type="hidden" id="plan-id" name="plan_id" value="{{ $plan->id }}">
                        
                        <!-- Payment Method Tabs -->
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <button type="button" 
                                        class="payment-tab py-2 px-1 border-b-2 font-medium text-sm focus:outline-none"
                                        data-tab="card"
                                        id="card-tab">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                        Credit Card
                                    </div>
                                </button>
                                <button type="button" 
                                        class="payment-tab py-2 px-1 border-b-2 font-medium text-sm focus:outline-none"
                                        data-tab="bank"
                                        id="bank-tab">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                        Bank Transfer
                                    </div>
                                </button>
                            </nav>
                        </div>
                        
                        <!-- Credit Card Payment -->
                        <div id="card-payment" class="payment-method">
                            <!-- Cardholder Name -->
                            <div>
                                <label for="cardholder-name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Cardholder Name
                                </label>
                                <input type="text" 
                                       id="cardholder-name" 
                                       name="cardholder_name"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Full name on card"
                                       required>
                            </div>
                            
                            <!-- Stripe Card Element -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Card Details
                                </label>
                                <div id="card-element" class="p-3 border border-gray-300 rounded-md bg-white">
                                    <!-- Stripe Elements will create form elements here -->
                                </div>
                                <div id="card-errors" class="mt-2 text-red-600 text-sm hidden"></div>
                            </div>
                            
                            <!-- Save Payment Method -->
                            <div class="flex items-center">
                                <input id="save-payment-method" 
                                       name="save_payment_method" 
                                       type="checkbox" 
                                       checked
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="save-payment-method" class="ml-2 block text-sm text-gray-700">
                                    Save payment method for future use
                                </label>
                            </div>
                        </div>
                        
                        <!-- Bank Transfer Payment -->
                        <div id="bank-payment" class="payment-method hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                <div class="flex">
                                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">Bank Transfer Instructions</h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>After clicking "Process Payment", you'll receive bank transfer instructions via email.</p>
                                            <p>Your subscription will be activated once payment is received (1-3 business days).</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Billing Address -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Billing Address</h3>
                            
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label for="address-line1" class="block text-sm font-medium text-gray-700">
                                        Address Line 1
                                    </label>
                                    <input type="text" 
                                           id="address-line1" 
                                           name="address_line1"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>
                                
                                <div class="sm:col-span-2">
                                    <label for="address-line2" class="block text-sm font-medium text-gray-700">
                                        Address Line 2 (Optional)
                                    </label>
                                    <input type="text" 
                                           id="address-line2" 
                                           name="address_line2"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-700">
                                        City
                                    </label>
                                    <input type="text" 
                                           id="city" 
                                           name="city"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>
                                
                                <div>
                                    <label for="postal-code" class="block text-sm font-medium text-gray-700">
                                        Postal Code
                                    </label>
                                    <input type="text" 
                                           id="postal-code" 
                                           name="postal_code"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="state" class="block text-sm font-medium text-gray-700">
                                        State/Province
                                    </label>
                                    <input type="text" 
                                           id="state" 
                                           name="state_code"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="country" class="block text-sm font-medium text-gray-700">
                                        Country
                                    </label>
                                    <select id="country" 
                                            name="country_code"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            required>
                                        <option value="">Select Country</option>
                                        <option value="US">United States</option>
                                        <option value="CA">Canada</option>
                                        <option value="GB">United Kingdom</option>
                                        <option value="DE">Germany</option>
                                        <option value="FR">France</option>
                                        <option value="IT">Italy</option>
                                        <option value="ES">Spain</option>
                                        <option value="NL">Netherlands</option>
                                        <option value="AU">Australia</option>
                                        <!-- Add more countries as needed -->
                                    </select>
                                </div>
                            </div>
                            
                            <!-- VAT/Tax ID (for EU) -->
                            <div id="tax-id-field" class="hidden">
                                <label for="tax-id" class="block text-sm font-medium text-gray-700">
                                    VAT/Tax ID (Optional)
                                </label>
                                <input type="text" 
                                       id="tax-id" 
                                       name="tax_id"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g. DE123456789">
                                <p class="mt-1 text-xs text-gray-500">For EU customers with valid VAT numbers</p>
                            </div>
                        </div>
                        
                        <!-- Terms and Conditions -->
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="terms" 
                                       name="terms" 
                                       type="checkbox" 
                                       required
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="terms" class="text-gray-700">
                                    I agree to the <a href="/terms" class="text-blue-600 hover:text-blue-500" target="_blank">Terms of Service</a>
                                    and <a href="/privacy" class="text-blue-600 hover:text-blue-500" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" 
                                id="submit-button"
                                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span id="button-text">Process Payment</span>
                            <svg class="animate-spin -mr-1 ml-3 h-5 w-5 text-white hidden" id="loading-spinner" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                        
                        <!-- Security Notice -->
                        <div class="flex items-center justify-center text-sm text-gray-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Secured by 256-bit SSL encryption
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="mt-8 lg:mt-0 lg:col-span-1">
                <div class="bg-white shadow-lg rounded-lg sticky top-8">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Order Summary</h2>
                    </div>
                    
                    <div class="p-6">
                        <!-- Plan Details -->
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900">{{ $plan->name }}</h3>
                                <p class="text-gray-600 text-sm mt-1">{{ $plan->description }}</p>
                                <p class="text-gray-500 text-sm">{{ $plan->getBillingPeriodDisplayAttribute() }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-medium text-gray-900">${{ number_format($plan->price, 2) }}</p>
                            </div>
                        </div>
                        
                        <!-- Plan Features -->
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">What's included:</h4>
                            <ul class="space-y-2">
                                @foreach($plan->features as $feature)
                                <li class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ ucwords(str_replace('_', ' ', $feature)) }}
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        
                        <!-- Pricing Breakdown -->
                        <div class="space-y-3 border-t border-gray-200 pt-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="text-gray-900" id="subtotal">${{ number_format($plan->price, 2) }}</span>
                            </div>
                            
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Tax</span>
                                <span class="text-gray-900" id="tax-amount">Calculated at checkout</span>
                            </div>
                            
                            @if($plan->trial_days > 0)
                            <div class="flex justify-between text-sm text-green-600">
                                <span>{{ $plan->trial_days }}-day free trial</span>
                                <span>$0.00</span>
                            </div>
                            @endif
                            
                            <div class="border-t border-gray-200 pt-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-base font-medium text-gray-900">Total</span>
                                    <span class="text-xl font-bold text-gray-900" id="total-amount">
                                        @if($plan->trial_days > 0)
                                            $0.00 today
                                        @else
                                            ${{ number_format($plan->price, 2) }}
                                        @endif
                                    </span>
                                </div>
                                @if($plan->trial_days > 0)
                                <p class="text-xs text-gray-500 mt-1">
                                    Then ${{ number_format($plan->price, 2) }}/{{ $plan->billing_period === 'monthly' ? 'month' : 'year' }}
                                </p>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Money-back Guarantee -->
                        <div class="mt-6 p-4 bg-green-50 rounded-lg">
                            <div class="flex">
                                <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-green-800">30-day money-back guarantee</h4>
                                    <p class="text-xs text-green-700 mt-1">Cancel anytime within 30 days for a full refund.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Processing Modal -->
<div id="payment-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                <svg class="animate-spin h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Processing Payment</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Please wait while we securely process your payment. This may take a few moments.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Stripe
    const stripe = Stripe('{{ config('services.stripe.key') }}');
    const elements = stripe.elements();
    
    // Payment form handling
    document.addEventListener('DOMContentLoaded', function() {
        initializePaymentForm();
    });
</script>
@endsection

@section('scripts')
<script src="{{ asset('js/payment-checkout.js') }}"></script>
@endsection