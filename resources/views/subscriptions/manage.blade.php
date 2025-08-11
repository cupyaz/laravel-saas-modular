@extends('layouts.app')

@section('title', 'Manage Subscription')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Subscription Management</h1>
            <p class="mt-2 text-gray-600">Manage your subscription, billing, and preferences</p>
        </div>

        <!-- Loading State -->
        <div id="loading-state" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Loading subscription data...</p>
        </div>

        <!-- Main Content -->
        <div id="subscription-content" class="hidden">
            <!-- Current Subscription Card -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Current Subscription</h2>
                </div>
                <div id="current-subscription" class="p-6">
                    <!-- Dynamic content loaded via JS -->
                </div>
            </div>

            <!-- Available Plans -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Available Plans</h2>
                    <p class="text-gray-600">Upgrade or downgrade your subscription</p>
                </div>
                <div id="available-plans" class="p-6">
                    <!-- Dynamic content loaded via JS -->
                </div>
            </div>

            <!-- Retention Offers (shown only if available) -->
            <div id="retention-offers-section" class="bg-gradient-to-r from-blue-50 to-indigo-50 shadow rounded-lg mb-8 hidden">
                <div class="px-6 py-4 border-b border-blue-200">
                    <h2 class="text-lg font-semibold text-blue-900">Special Offer Just for You!</h2>
                    <p class="text-blue-700">Don't miss this limited-time opportunity</p>
                </div>
                <div id="retention-offers" class="p-6">
                    <!-- Dynamic content loaded via JS -->
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div id="error-state" class="hidden bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error Loading Subscription</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p id="error-message">Failed to load subscription data. Please try again.</p>
                    </div>
                    <div class="mt-4">
                        <button onclick="loadSubscriptionData()" class="bg-red-100 hover:bg-red-200 text-red-800 px-4 py-2 rounded-md text-sm font-medium">
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Plan Change Confirmation Modal -->
<div id="plan-change-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div>
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-5">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        Change Subscription Plan
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500" id="plan-change-description">
                            Are you sure you want to change your subscription plan?
                        </p>
                    </div>
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <div id="proration-details" class="text-sm">
                            <!-- Proration details will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                <button type="button" id="confirm-plan-change" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                    Confirm Change
                </button>
                <button type="button" onclick="closePlanChangeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cancellation Modal -->
<div id="cancellation-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <form id="cancellation-form">
                <div>
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.314 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Cancel Subscription
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                We're sorry to see you go. Please help us improve by telling us why you're canceling.
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label for="cancellation-reason" class="block text-sm font-medium text-gray-700">Reason for cancellation</label>
                            <select id="cancellation-reason" name="reason" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">Select a reason</option>
                                <option value="too_expensive">Too expensive</option>
                                <option value="not_using">Not using enough</option>
                                <option value="missing_features">Missing features</option>
                                <option value="found_alternative">Found alternative</option>
                                <option value="technical_issues">Technical issues</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="cancellation-feedback" class="block text-sm font-medium text-gray-700">Additional feedback (optional)</label>
                            <textarea id="cancellation-feedback" name="feedback" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="Help us understand how we can improve..."></textarea>
                        </div>
                        <div class="flex items-center">
                            <input id="immediate-cancellation" name="immediate" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="immediate-cancellation" class="ml-2 block text-sm text-gray-700">Cancel immediately (otherwise you'll have access until the end of your billing period)</label>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:col-start-2 sm:text-sm">
                        Cancel Subscription
                    </button>
                    <button type="button" onclick="closeCancellationModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                        Keep Subscription
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('js/subscription-management.js') }}"></script>
@endsection