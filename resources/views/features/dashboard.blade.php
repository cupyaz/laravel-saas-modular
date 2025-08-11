@extends('layouts.app')

@section('title', 'Feature Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Feature Dashboard</h1>
            <p class="mt-2 text-gray-600">Manage your features and usage limits</p>
        </div>

        <!-- Loading State -->
        <div id="loading-state" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Loading feature data...</p>
        </div>

        <!-- Main Content -->
        <div id="feature-content" class="hidden">
            <!-- Current Plan Overview -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Current Plan</h2>
                        <span id="plan-badge" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium">
                            <!-- Plan badge will be populated -->
                        </span>
                    </div>
                </div>
                <div id="plan-overview" class="p-6">
                    <!-- Dynamic content loaded via JS -->
                </div>
            </div>

            <!-- Usage Summary -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Usage Summary</h2>
                    <p class="text-gray-600">Track your feature usage and limits</p>
                </div>
                <div id="usage-summary" class="p-6">
                    <!-- Dynamic content loaded via JS -->
                </div>
            </div>

            <!-- Feature Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div id="features-grid">
                    <!-- Feature cards will be populated -->
                </div>
            </div>

            <!-- Upgrade Recommendations -->
            <div id="recommendations-section" class="bg-gradient-to-r from-blue-50 to-indigo-50 shadow rounded-lg mb-8 hidden">
                <div class="px-6 py-4 border-b border-blue-200">
                    <h2 class="text-lg font-semibold text-blue-900">Recommendations</h2>
                    <p class="text-blue-700">Unlock more features and increase your limits</p>
                </div>
                <div id="recommendations" class="p-6">
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
                    <h3 class="text-sm font-medium text-red-800">Error Loading Features</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p id="error-message">Failed to load feature data. Please try again.</p>
                    </div>
                    <div class="mt-4">
                        <button onclick="loadFeatureData()" class="bg-red-100 hover:bg-red-200 text-red-800 px-4 py-2 rounded-md text-sm font-medium">
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Feature Test Modal -->
<div id="test-feature-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div>
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-5">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="test-modal-title">
                        Test Feature
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500" id="test-modal-description">
                            Test this feature to see how it works with your current plan.
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                <button type="button" id="confirm-test-feature" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                    Test Feature
                </button>
                <button type="button" onclick="closeTestModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                    Cancel
                </button>
            </div>
            <div id="test-result" class="mt-4 hidden">
                <!-- Test result will be shown here -->
            </div>
        </div>
    </div>
</div>

<!-- Upgrade Modal -->
<div id="upgrade-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6">
            <div>
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-5">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="upgrade-modal-title">
                        Upgrade Your Plan
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500" id="upgrade-modal-description">
                            Choose a plan that fits your needs.
                        </p>
                    </div>
                </div>
                <div class="mt-6">
                    <div id="upgrade-plans" class="space-y-4">
                        <!-- Upgrade plans will be populated -->
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-6">
                <button type="button" onclick="closeUpgradeModal()" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('js/feature-dashboard.js') }}"></script>
@endsection