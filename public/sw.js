// Service Worker for PWA functionality
const CACHE_NAME = 'saas-platform-v1';
const OFFLINE_URL = '/offline.html';

// Resources to cache for offline functionality
const CACHE_URLS = [
    '/',
    '/dashboard',
    '/usage',
    '/plans',
    '/profile',
    '/offline.html',
    '/css/app.css',
    '/js/app.js',
    '/js/mobile-navigation.js',
    '/js/touch-gestures.js',
    '/manifest.json'
];

// API endpoints that should be cached
const API_CACHE_PATTERNS = [
    /\/api\/v1\/usage\/summary/,
    /\/api\/v1\/usage\/meters/,
    /\/api\/v1\/plans/,
    /\/api\/v1\/upgrade-prompts\/recommendations/
];

// Install event - cache resources
self.addEventListener('install', event => {
    console.log('Service Worker: Installing');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Service Worker: Caching files');
                return cache.addAll(CACHE_URLS);
            })
            .then(() => {
                console.log('Service Worker: Cached all files');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker: Cache failed', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== CACHE_NAME) {
                            console.log('Service Worker: Deleting old cache', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: Claiming clients');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-HTTP requests
    if (!request.url.startsWith('http')) {
        return;
    }
    
    // Handle navigation requests
    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(request));
        return;
    }
    
    // Handle API requests
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(handleApiRequest(request));
        return;
    }
    
    // Handle static assets
    event.respondWith(handleStaticRequest(request));
});

// Handle navigation requests (pages)
async function handleNavigationRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Network failed, serving from cache');
        
        // Try to serve from cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Serve offline page
        return caches.match(OFFLINE_URL);
    }
}

// Handle API requests with caching strategy
async function handleApiRequest(request) {
    const url = new URL(request.url);
    const shouldCache = API_CACHE_PATTERNS.some(pattern => pattern.test(url.pathname));
    
    if (request.method !== 'GET' || !shouldCache) {
        // For non-GET requests or non-cacheable APIs, try network only
        try {
            return await fetch(request);
        } catch (error) {
            return new Response(JSON.stringify({
                error: 'Network unavailable',
                message: 'Please check your connection and try again'
            }), {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            });
        }
    }
    
    // For cacheable GET requests, use cache-first strategy
    try {
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            // Serve from cache and update in background
            fetchAndCache(request);
            return cachedResponse;
        }
        
        // No cache, fetch from network
        return await fetchAndCache(request);
    } catch (error) {
        console.error('Service Worker: API request failed', error);
        return new Response(JSON.stringify({
            error: 'Service unavailable',
            message: 'Unable to load data'
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Handle static assets (CSS, JS, images)
async function handleStaticRequest(request) {
    try {
        // Try cache first for static assets
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Fetch from network and cache
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Static request failed', error);
        
        // For critical assets, return cached version or placeholder
        if (request.url.includes('.css')) {
            return new Response('/* Offline CSS */', {
                headers: { 'Content-Type': 'text/css' }
            });
        }
        
        if (request.url.includes('.js')) {
            return new Response('/* Offline JS */', {
                headers: { 'Content-Type': 'application/javascript' }
            });
        }
        
        // For other assets, return empty response
        return new Response();
    }
}

// Fetch and cache helper function
async function fetchAndCache(request) {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
}

// Handle background sync for offline actions
self.addEventListener('sync', event => {
    console.log('Service Worker: Background sync', event.tag);
    
    if (event.tag === 'usage-tracking') {
        event.waitUntil(syncUsageData());
    }
    
    if (event.tag === 'form-submission') {
        event.waitUntil(syncFormSubmissions());
    }
});

// Sync usage data when back online
async function syncUsageData() {
    try {
        // Get pending usage data from IndexedDB
        const pendingData = await getPendingUsageData();
        
        for (const data of pendingData) {
            try {
                const response = await fetch('/api/v1/usage/track', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': await getCSRFToken()
                    },
                    body: JSON.stringify(data)
                });
                
                if (response.ok) {
                    await removePendingUsageData(data.id);
                }
            } catch (error) {
                console.error('Failed to sync usage data:', error);
            }
        }
    } catch (error) {
        console.error('Service Worker: Background sync failed', error);
    }
}

// Sync form submissions when back online
async function syncFormSubmissions() {
    try {
        const pendingForms = await getPendingFormSubmissions();
        
        for (const form of pendingForms) {
            try {
                const response = await fetch(form.url, {
                    method: form.method,
                    headers: form.headers,
                    body: form.data
                });
                
                if (response.ok) {
                    await removePendingFormSubmission(form.id);
                    
                    // Notify the page of successful sync
                    const clients = await self.clients.matchAll();
                    clients.forEach(client => {
                        client.postMessage({
                            type: 'FORM_SYNCED',
                            formId: form.id
                        });
                    });
                }
            } catch (error) {
                console.error('Failed to sync form submission:', error);
            }
        }
    } catch (error) {
        console.error('Service Worker: Form sync failed', error);
    }
}

// Push notification handler
self.addEventListener('push', event => {
    if (!event.data) return;
    
    const data = event.data.json();
    const options = {
        body: data.body,
        icon: '/images/icons/icon-192x192.png',
        badge: '/images/icons/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: data.data,
        actions: data.actions || []
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    const urlToOpen = event.notification.data?.url || '/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window' })
            .then(clientList => {
                // Check if there's already a window open
                for (const client of clientList) {
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }
                
                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// Helper functions for IndexedDB operations
async function getPendingUsageData() {
    // Implementation would depend on your IndexedDB setup
    return [];
}

async function removePendingUsageData(id) {
    // Implementation would depend on your IndexedDB setup
}

async function getPendingFormSubmissions() {
    // Implementation would depend on your IndexedDB setup
    return [];
}

async function removePendingFormSubmission(id) {
    // Implementation would depend on your IndexedDB setup
}

async function getCSRFToken() {
    const response = await fetch('/csrf-token');
    const data = await response.json();
    return data.token;
}