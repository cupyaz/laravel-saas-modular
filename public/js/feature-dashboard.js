/**
 * Feature Dashboard JavaScript
 * Handles freemium feature management and upgrade prompts
 */

class FeatureDashboard {
    constructor() {
        this.features = {};
        this.usageSummary = [];
        this.recommendations = [];
        this.tenant = null;
        this.selectedTestFeature = null;
        
        this.init();
    }
    
    init() {
        this.loadFeatureData();
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        // Test feature confirmation
        document.getElementById('confirm-test-feature')?.addEventListener('click', () => {
            this.testSelectedFeature();
        });
    }
    
    async loadFeatureData() {
        try {
            this.showLoading();
            
            // Load features and usage data
            const [featuresResponse, usageResponse, recommendationsResponse] = await Promise.all([
                this.apiCall('/api/v1/features'),
                this.apiCall('/api/v1/features/usage/summary'),
                this.apiCall('/api/v1/features/recommendations'),
            ]);
            
            if (featuresResponse.ok && usageResponse.ok) {
                const featuresData = await featuresResponse.json();
                const usageData = await usageResponse.json();
                const recommendationsData = recommendationsResponse.ok ? await recommendationsResponse.json() : { recommendations: [] };
                
                this.features = featuresData.features || {};
                this.tenant = featuresData.tenant;
                this.usageSummary = usageData.usage_summary || [];
                this.recommendations = recommendationsData.recommendations || [];
                
                this.renderDashboard();
                this.showContent();
            } else {
                throw new Error('Failed to load feature data');
            }
        } catch (error) {
            console.error('Error loading feature data:', error);
            this.showError(error.message);
        }
    }
    
    renderDashboard() {
        this.renderPlanOverview();
        this.renderUsageSummary();
        this.renderFeaturesGrid();
        this.renderRecommendations();
    }
    
    renderPlanOverview() {
        const container = document.getElementById('plan-overview');
        const badge = document.getElementById('plan-badge');
        
        if (!this.tenant) {
            container.innerHTML = '<p class="text-gray-500">No tenant information available</p>';
            return;
        }
        
        const isFreeTier = this.tenant.is_free_tier;
        const planName = this.tenant.current_plan || 'Free';
        
        // Update plan badge
        badge.textContent = planName;
        badge.className = `inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
            isFreeTier ? 'bg-gray-100 text-gray-800' : 'bg-blue-100 text-blue-800'
        }`;
        
        container.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <h3 class="text-lg font-semibold ${isFreeTier ? 'text-gray-900' : 'text-blue-900'}">${planName} Plan</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        ${isFreeTier ? 'Start with our free features' : 'Premium features unlocked'}
                    </p>
                    ${isFreeTier ? `
                        <button onclick="featureDashboard.showUpgradeModal()" 
                            class="mt-2 inline-flex items-center px-3 py-1 rounded-md text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">
                            Upgrade Plan
                        </button>
                    ` : ''}
                </div>
                <div class="text-center">
                    <h4 class="text-2xl font-bold text-gray-900">${Object.keys(this.features).length}</h4>
                    <p class="text-sm text-gray-600">Available Features</p>
                </div>
                <div class="text-center">
                    <h4 class="text-2xl font-bold text-gray-900">${this.usageSummary.filter(item => item.is_approaching_limit).length}</h4>
                    <p class="text-sm text-gray-600">Approaching Limits</p>
                </div>
            </div>
        `;
    }
    
    renderUsageSummary() {
        const container = document.getElementById('usage-summary');
        
        if (this.usageSummary.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Usage Data</h3>
                    <p class="mt-1 text-sm text-gray-500">Start using features to see your usage statistics.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="space-y-4">
                ${this.usageSummary.map(item => this.renderUsageItem(item)).join('')}
            </div>
        `;
    }
    
    renderUsageItem(item) {
        const percentage = item.percentage_used;
        const isApproaching = item.is_approaching_limit;
        const barColor = percentage > 90 ? 'bg-red-500' : percentage > 70 ? 'bg-yellow-500' : 'bg-green-500';
        const textColor = isApproaching ? 'text-red-600' : 'text-gray-900';
        
        return `
            <div class="bg-gray-50 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-medium ${textColor}">${item.feature}</h4>
                    ${isApproaching ? '<span class="text-xs text-red-600 font-medium">Approaching Limit</span>' : ''}
                </div>
                <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                    <span>${item.current_usage} / ${item.limit_value} ${item.limit_type.replace('_', ' ')}</span>
                    <span>${Math.round(percentage)}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="${barColor} h-2 rounded-full transition-all duration-300" style="width: ${percentage}%"></div>
                </div>
                ${isApproaching ? `
                    <div class="mt-2">
                        <button onclick="featureDashboard.showUpgradeModal('${item.feature}')" 
                            class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                            Upgrade to increase limit →
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    renderFeaturesGrid() {
        const container = document.getElementById('features-grid');
        
        const featureCards = Object.entries(this.features).map(([key, feature]) => 
            this.renderFeatureCard(key, feature)
        ).join('');
        
        container.innerHTML = featureCards;
    }
    
    renderFeatureCard(key, feature) {
        const hasAccess = feature.has_access;
        const isPremium = feature.is_premium;
        const limits = feature.limits;
        const usage = feature.usage;
        
        const cardClass = hasAccess ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50';
        const statusIcon = hasAccess ? 
            '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' :
            '<svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>';
        
        return `
            <div class="border rounded-lg p-4 ${cardClass} transition-all duration-200 hover:shadow-md">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center">
                        ${statusIcon}
                        <h3 class="ml-2 font-medium text-gray-900">${feature.name}</h3>
                    </div>
                    ${isPremium ? '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Premium</span>' : ''}
                </div>
                
                <p class="text-sm text-gray-600 mb-4">${feature.description}</p>
                
                ${limits ? this.renderFeatureLimits(limits, usage) : ''}
                
                <div class="flex space-x-2 mt-4">
                    ${hasAccess ? `
                        <button onclick="featureDashboard.testFeature('${key}')" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-3 rounded-md transition-colors">
                            Test Feature
                        </button>
                    ` : `
                        <button onclick="featureDashboard.showUpgradeModal('${key}')" 
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 text-sm font-medium py-2 px-3 rounded-md transition-colors">
                            Upgrade Required
                        </button>
                    `}
                    <button onclick="featureDashboard.showFeatureInfo('${key}')" 
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-3 rounded-md transition-colors">
                        Info
                    </button>
                </div>
            </div>
        `;
    }
    
    renderFeatureLimits(limits, usage) {
        if (!limits || Object.keys(limits).length === 0) {
            return '';
        }
        
        return `
            <div class="space-y-2 mb-3">
                ${Object.entries(limits).map(([limitType, limitValue]) => {
                    if (limitValue === -1) {
                        return `<div class="text-sm text-green-600 font-medium">✓ Unlimited ${limitType.replace('_', ' ')}</div>`;
                    }
                    
                    const usageInfo = usage?.[limitType];
                    if (usageInfo) {
                        const percentage = (usageInfo.current_usage / usageInfo.limit_value) * 100;
                        const color = percentage > 80 ? 'text-red-600' : 'text-gray-600';
                        return `
                            <div class="text-sm ${color}">
                                ${usageInfo.current_usage}/${limitValue} ${limitType.replace('_', ' ')}
                            </div>
                        `;
                    } else {
                        return `<div class="text-sm text-gray-600">Up to ${limitValue} ${limitType.replace('_', ' ')}</div>`;
                    }
                }).join('')}
            </div>
        `;
    }
    
    renderRecommendations() {
        const container = document.getElementById('recommendations');
        const section = document.getElementById('recommendations-section');
        
        if (!this.recommendations || this.recommendations.length === 0) {
            section.classList.add('hidden');
            return;
        }
        
        section.classList.remove('hidden');
        
        container.innerHTML = `
            <div class="space-y-6">
                ${this.recommendations.map(rec => this.renderRecommendation(rec)).join('')}
            </div>
        `;
    }
    
    renderRecommendation(recommendation) {
        const priorityClass = recommendation.priority === 'high' ? 'border-red-200 bg-red-50' : 'border-blue-200 bg-blue-50';
        const priorityIcon = recommendation.priority === 'high' ? 
            '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.314 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>' :
            '<svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        
        return `
            <div class="border rounded-lg p-4 ${priorityClass}">
                <div class="flex items-start">
                    ${priorityIcon}
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-gray-900">${recommendation.title}</h3>
                        <p class="mt-1 text-sm text-gray-600">${recommendation.description}</p>
                        <div class="mt-3">
                            <button onclick="featureDashboard.showUpgradeModal()" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                View Upgrade Options
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    async testFeature(featureKey) {
        this.selectedTestFeature = featureKey;
        const feature = this.features[featureKey];
        
        document.getElementById('test-modal-title').textContent = `Test ${feature.name}`;
        document.getElementById('test-modal-description').textContent = 
            `Click "Test Feature" to try ${feature.name} and see how it works with your current plan.`;
        
        this.showTestModal();
    }
    
    async testSelectedFeature() {
        if (!this.selectedTestFeature) return;
        
        try {
            const testButton = document.getElementById('confirm-test-feature');
            const resultDiv = document.getElementById('test-result');
            
            testButton.disabled = true;
            testButton.textContent = 'Testing...';
            
            let endpoint = null;
            let method = 'POST';
            let body = {};
            
            // Map feature keys to test endpoints
            switch (this.selectedTestFeature) {
                case 'basic_reports':
                    endpoint = '/api/v1/examples/reports/basic';
                    break;
                case 'file_storage':
                    endpoint = '/api/v1/examples/files/upload';
                    // Create a mock file for testing
                    const formData = new FormData();
                    formData.append('file', new Blob(['test content'], { type: 'text/plain' }), 'test.txt');
                    body = formData;
                    break;
                case 'projects':
                    endpoint = '/api/v1/examples/projects';
                    body = { name: 'Test Project', description: 'Testing project creation' };
                    break;
                case 'api_access':
                    endpoint = '/api/v1/examples/api-call';
                    break;
                case 'advanced_analytics':
                    endpoint = '/api/v1/examples/analytics/advanced';
                    method = 'GET';
                    break;
                case 'custom_branding':
                    endpoint = '/api/v1/examples/branding';
                    method = 'GET';
                    break;
                case 'export_data':
                    endpoint = '/api/v1/examples/export';
                    body = { format: 'json', data_type: 'users' };
                    break;
                default:
                    throw new Error('Unknown feature test endpoint');
            }
            
            const options = { method };
            if (method === 'POST') {
                if (body instanceof FormData) {
                    options.body = body;
                } else {
                    options.body = JSON.stringify(body);
                }
            }
            
            const response = await this.apiCall(endpoint, options);
            const result = await response.json();
            
            resultDiv.classList.remove('hidden');
            
            if (response.ok) {
                resultDiv.innerHTML = `
                    <div class="p-4 bg-green-50 border border-green-200 rounded-md">
                        <div class="flex">
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Feature Test Successful!</h3>
                                <div class="mt-2 text-sm text-green-700">
                                    <p>${result.message || 'Feature executed successfully'}</p>
                                    ${result.usage_info ? `
                                        <p class="mt-1">Usage: ${result.usage_info.current_usage}/${result.usage_info.limit_value || '∞'}</p>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Refresh usage data
                setTimeout(() => this.loadFeatureData(), 1000);
            } else {
                resultDiv.innerHTML = `
                    <div class="p-4 bg-red-50 border border-red-200 rounded-md">
                        <div class="flex">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Feature Test Failed</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p>${result.message || 'Feature access denied'}</p>
                                    ${result.upgrade_info ? `
                                        <div class="mt-2">
                                            <button onclick="featureDashboard.showUpgradeModal('${this.selectedTestFeature}')" 
                                                class="text-red-800 hover:text-red-900 font-medium underline">
                                                View Upgrade Options →
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Feature test failed:', error);
            document.getElementById('test-result').innerHTML = `
                <div class="p-4 bg-red-50 border border-red-200 rounded-md">
                    <div class="text-sm text-red-700">
                        <p>Test failed: ${error.message}</p>
                    </div>
                </div>
            `;
        } finally {
            testButton.disabled = false;
            testButton.textContent = 'Test Feature';
        }
    }
    
    async showUpgradeModal(featureKey = null) {
        // In a real implementation, this would show upgrade options
        // For now, redirect to subscription management
        window.location.href = '/subscriptions/manage';
    }
    
    showFeatureInfo(featureKey) {
        const feature = this.features[featureKey];
        alert(`${feature.name}\n\n${feature.description}\n\nAccess: ${feature.has_access ? 'Available' : 'Upgrade Required'}`);
    }
    
    // UI Helper Methods
    showLoading() {
        document.getElementById('loading-state').classList.remove('hidden');
        document.getElementById('feature-content').classList.add('hidden');
        document.getElementById('error-state').classList.add('hidden');
    }
    
    showContent() {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('feature-content').classList.remove('hidden');
        document.getElementById('error-state').classList.add('hidden');
    }
    
    showError(message) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('feature-content').classList.add('hidden');
        document.getElementById('error-state').classList.remove('hidden');
        document.getElementById('error-message').textContent = message;
    }
    
    showTestModal() {
        document.getElementById('test-feature-modal').classList.remove('hidden');
    }
    
    showNotification(message, type = 'info') {
        // Create a simple notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg ${
            type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
            type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
            type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
            'bg-blue-100 text-blue-800 border border-blue-200'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-current hover:opacity-75">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    // API Helper Methods
    async apiCall(endpoint, options = {}) {
        const token = localStorage.getItem('auth_token');
        
        const defaultOptions = {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                ...(token && { 'Authorization': `Bearer ${token}` })
            }
        };
        
        // Don't set Content-Type for FormData
        if (!(options.body instanceof FormData)) {
            defaultOptions.headers['Content-Type'] = 'application/json';
        }
        
        return fetch(endpoint, { ...defaultOptions, ...options });
    }
}

// Global functions for modal interactions
function closeTestModal() {
    document.getElementById('test-feature-modal').classList.add('hidden');
    document.getElementById('test-result').classList.add('hidden');
}

function closeUpgradeModal() {
    document.getElementById('upgrade-modal').classList.add('hidden');
}

function loadFeatureData() {
    if (window.featureDashboard) {
        window.featureDashboard.loadFeatureData();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.featureDashboard = new FeatureDashboard();
});