<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel SaaS') }} - Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Create your account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                    sign in to your existing account
                </a>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <form id="register-form" class="space-y-6" method="POST" action="{{ route('register.store') }}">
                    @csrf

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            Full Name
                        </label>
                        <div class="mt-1">
                            <input id="name" name="name" type="text" required 
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                   value="{{ old('name') }}">
                        </div>
                        <div class="error-message text-red-600 text-sm mt-1" id="name-error"></div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email Address
                        </label>
                        <div class="mt-1">
                            <input id="email" name="email" type="email" required
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                   value="{{ old('email') }}">
                        </div>
                        <div class="error-message text-red-600 text-sm mt-1" id="email-error"></div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <div class="mt-1">
                            <input id="password" name="password" type="password" required
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Must be at least 8 characters with mixed case, numbers, and symbols
                        </p>
                        <div class="error-message text-red-600 text-sm mt-1" id="password-error"></div>
                    </div>

                    <!-- Password Confirmation -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                            Confirm Password
                        </label>
                        <div class="mt-1">
                            <input id="password_confirmation" name="password_confirmation" type="password" required
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div class="error-message text-red-600 text-sm mt-1" id="password_confirmation-error"></div>
                    </div>

                    <!-- GDPR Consent -->
                    <div class="flex items-center">
                        <input id="gdpr_consent" name="gdpr_consent" type="checkbox" required
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="gdpr_consent" class="ml-2 block text-sm text-gray-900">
                            I agree to the 
                            <a href="/privacy" target="_blank" class="text-indigo-600 hover:text-indigo-500">Privacy Policy</a>
                            and 
                            <a href="/terms" target="_blank" class="text-indigo-600 hover:text-indigo-500">Terms of Service</a>
                        </label>
                    </div>
                    <div class="error-message text-red-600 text-sm" id="gdpr_consent-error"></div>

                    <!-- Submit Button -->
                    <div>
                        <button type="submit" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                id="submit-btn">
                            <span class="submit-text">Create Account</span>
                            <span class="loading-text hidden">Creating Account...</span>
                        </button>
                    </div>
                </form>

                <!-- Success Message -->
                <div id="success-message" class="hidden mt-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    Registration successful! Please check your email to verify your account.
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('register-form');
            const submitBtn = document.getElementById('submit-btn');
            const submitText = submitBtn.querySelector('.submit-text');
            const loadingText = submitBtn.querySelector('.loading-text');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearErrors();
                
                // Show loading state
                submitBtn.disabled = true;
                submitText.classList.add('hidden');
                loadingText.classList.remove('hidden');

                const formData = new FormData(form);

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Show success message
                        document.getElementById('success-message').classList.remove('hidden');
                        form.style.display = 'none';
                    } else {
                        // Show validation errors
                        if (data.errors) {
                            showErrors(data.errors);
                        } else if (data.message) {
                            alert(data.message);
                        }
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    alert('An error occurred during registration. Please try again.');
                } finally {
                    // Reset loading state
                    submitBtn.disabled = false;
                    submitText.classList.remove('hidden');
                    loadingText.classList.add('hidden');
                }
            });

            function clearErrors() {
                const errorElements = document.querySelectorAll('.error-message');
                errorElements.forEach(el => el.textContent = '');
                
                const inputs = document.querySelectorAll('input');
                inputs.forEach(input => {
                    input.classList.remove('border-red-500');
                    input.classList.add('border-gray-300');
                });
            }

            function showErrors(errors) {
                for (const [field, messages] of Object.entries(errors)) {
                    const errorElement = document.getElementById(field + '-error');
                    const inputElement = document.getElementById(field);
                    
                    if (errorElement) {
                        errorElement.textContent = messages[0];
                    }
                    
                    if (inputElement) {
                        inputElement.classList.remove('border-gray-300');
                        inputElement.classList.add('border-red-500');
                    }
                }
            }
        });
    </script>
</body>
</html>