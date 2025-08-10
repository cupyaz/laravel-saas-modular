<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>US-006 Payment Test</title>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .StripeElement { padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; }
    </style>
</head>
<body class="bg-gray-50 py-8">
    <div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold mb-6 text-center">🧪 US-006 Payment Test</h1>
        
        <!-- Plan Info -->
        <div class="bg-blue-50 p-4 rounded mb-6">
            <h3 class="font-semibold">Premium Plan</h3>
            <p class="text-sm text-gray-600">Full access to all features</p>
            <p class="text-xl font-bold text-blue-600">$29.99/month</p>
        </div>

        <!-- Payment Form -->
        <form id="payment-form">
            <!-- Payment Method Tabs -->
            <div class="flex mb-4">
                <button type="button" id="card-tab" class="flex-1 py-2 px-4 text-sm font-medium bg-blue-100 text-blue-700 rounded-l">
                    💳 Credit Card
                </button>
                <button type="button" id="bank-tab" class="flex-1 py-2 px-4 text-sm font-medium bg-gray-100 text-gray-600 rounded-r">
                    🏦 Bank Transfer
                </button>
            </div>

            <!-- Credit Card Form -->
            <div id="card-payment" class="payment-method">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Cardholder Name</label>
                    <input type="text" id="cardholder-name" class="w-full px-3 py-2 border rounded" 
                           placeholder="John Doe" value="Test User">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Card Details</label>
                    <div id="card-element" class="StripeElement"></div>
                    <div id="card-errors" class="text-red-600 text-sm mt-2 hidden"></div>
                </div>
            </div>

            <!-- Bank Transfer Info -->
            <div id="bank-payment" class="payment-method hidden">
                <div class="bg-yellow-50 p-4 rounded">
                    <h4 class="font-medium text-yellow-800 mb-2">Bank Transfer Instructions</h4>
                    <p class="text-sm text-yellow-700">Click "Process Payment" to receive bank transfer instructions via email.</p>
                </div>
            </div>

            <!-- Billing Address -->
            <div class="mb-4">
                <h3 class="font-medium mb-3">Billing Address</h3>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <input type="text" placeholder="Address" class="px-3 py-2 border rounded" value="123 Test St">
                    <input type="text" placeholder="City" class="px-3 py-2 border rounded" value="Los Angeles">
                </div>
                <div class="grid grid-cols-3 gap-3 mb-3">
                    <select id="country" class="px-3 py-2 border rounded">
                        <option value="US">United States</option>
                        <option value="IT">Italy</option>
                        <option value="DE">Germany</option>
                        <option value="AE">UAE</option>
                    </select>
                    <input type="text" id="state" placeholder="State" class="px-3 py-2 border rounded" value="CA">
                    <input type="text" placeholder="ZIP" class="px-3 py-2 border rounded" value="90210">
                </div>
            </div>

            <!-- Tax Calculation Display -->
            <div class="bg-gray-50 p-4 rounded mb-4">
                <div class="flex justify-between text-sm">
                    <span>Subtotal:</span>
                    <span id="subtotal">$29.99</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span>Tax:</span>
                    <span id="tax-amount">Calculating...</span>
                </div>
                <div class="flex justify-between font-bold border-t pt-2 mt-2">
                    <span>Total:</span>
                    <span id="total-amount">$29.99</span>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" id="submit-button" 
                    class="w-full bg-blue-600 text-white py-3 rounded font-medium hover:bg-blue-700 disabled:opacity-50">
                <span id="button-text">Process Payment</span>
                <span id="loading" class="hidden">Processing...</span>
            </button>
        </form>

        <!-- Test Results -->
        <div id="test-results" class="mt-6 p-4 bg-green-50 rounded hidden">
            <h4 class="font-semibold text-green-800 mb-2">✅ Test Results</h4>
            <pre id="results-content" class="text-xs bg-white p-2 rounded overflow-auto max-h-40"></pre>
        </div>
    </div>

    <script>
        // Mock Stripe for testing (replace with real Stripe in production)
        const stripe = {
            elements: () => ({
                create: () => ({
                    mount: () => console.log('Card element mounted'),
                    on: () => console.log('Card element event listener added')
                })
            })
        };

        let currentPaymentMethod = 'card';
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🧪 US-006 Payment Test Page Loaded');
            
            // Tab switching
            document.getElementById('card-tab').addEventListener('click', () => {
                switchPaymentMethod('card');
            });
            
            document.getElementById('bank-tab').addEventListener('click', () => {
                switchPaymentMethod('bank');
            });
            
            // Tax calculation on country change
            document.getElementById('country').addEventListener('change', calculateTax);
            document.getElementById('state').addEventListener('change', calculateTax);
            
            // Form submission
            document.getElementById('payment-form').addEventListener('submit', handleSubmit);
            
            // Initial tax calculation
            calculateTax();
        });
        
        function switchPaymentMethod(method) {
            currentPaymentMethod = method;
            
            // Update tabs
            document.getElementById('card-tab').className = method === 'card' 
                ? 'flex-1 py-2 px-4 text-sm font-medium bg-blue-100 text-blue-700 rounded-l'
                : 'flex-1 py-2 px-4 text-sm font-medium bg-gray-100 text-gray-600 rounded-l';
                
            document.getElementById('bank-tab').className = method === 'bank'
                ? 'flex-1 py-2 px-4 text-sm font-medium bg-blue-100 text-blue-700 rounded-r' 
                : 'flex-1 py-2 px-4 text-sm font-medium bg-gray-100 text-gray-600 rounded-r';
            
            // Show/hide payment methods
            document.getElementById('card-payment').classList.toggle('hidden', method !== 'card');
            document.getElementById('bank-payment').classList.toggle('hidden', method !== 'bank');
            
            // Update button text
            document.getElementById('button-text').textContent = 
                method === 'bank' ? 'Get Bank Instructions' : 'Process Payment';
        }
        
        function calculateTax() {
            const country = document.getElementById('country').value;
            const state = document.getElementById('state').value;
            
            console.log('🧮 Calculating tax for:', country, state);
            
            // Mock tax calculation (same logic as backend)
            const subtotal = 29.99;
            let taxRate = 0;
            let taxType = '';
            
            if (country === 'US') {
                taxRate = state === 'CA' ? 0.0725 : 0.06;
                taxType = 'Sales Tax';
            } else if (['IT', 'DE', 'FR'].includes(country)) {
                taxRate = 0.20;
                taxType = 'VAT';
            }
            
            const taxAmount = subtotal * taxRate;
            const total = subtotal + taxAmount;
            
            // Update display
            document.getElementById('tax-amount').textContent = 
                taxAmount > 0 ? `$${taxAmount.toFixed(2)} (${taxType})` : '$0.00';
            document.getElementById('total-amount').textContent = `$${total.toFixed(2)}`;
            
            console.log('✅ Tax calculated:', {
                subtotal, taxRate: taxRate * 100 + '%', taxAmount, total
            });
        }
        
        function handleSubmit(e) {
            e.preventDefault();
            
            const button = document.getElementById('submit-button');
            const buttonText = document.getElementById('button-text');
            const loading = document.getElementById('loading');
            
            // Show loading state
            button.disabled = true;
            buttonText.classList.add('hidden');
            loading.classList.remove('hidden');
            
            console.log('💳 Processing payment with method:', currentPaymentMethod);
            
            // Simulate payment processing
            setTimeout(() => {
                const result = {
                    success: true,
                    method: currentPaymentMethod,
                    amount: document.getElementById('total-amount').textContent,
                    timestamp: new Date().toISOString()
                };
                
                showTestResults(result);
                
                // Reset button
                button.disabled = false;
                buttonText.classList.remove('hidden');
                loading.classList.add('hidden');
                
                console.log('✅ Payment test completed:', result);
            }, 2000);
        }
        
        function showTestResults(result) {
            const resultsDiv = document.getElementById('test-results');
            const resultsContent = document.getElementById('results-content');
            
            resultsContent.textContent = JSON.stringify(result, null, 2);
            resultsDiv.classList.remove('hidden');
        }
    </script>
</body>
</html>