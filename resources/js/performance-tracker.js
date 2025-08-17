/**
 * Performance tracking and optimization for mobile-first SaaS platform
 * Tracks Core Web Vitals and custom metrics
 */

class PerformanceTracker {
    constructor(options = {}) {
        this.options = {
            endpoint: '/api/v1/performance/track',
            batchSize: 10,
            batchTimeout: 5000,
            enableAutoTracking: true,
            enableResourceTracking: true,
            enableUserInteractionTracking: true,
            enableNavigationTracking: true,
            ...options
        };
        
        this.metrics = [];
        this.batch = [];
        this.batchTimeout = null;
        this.observer = null;
        this.navigationStartTime = performance.timeOrigin || performance.timing.navigationStart;
        
        this.init();
    }
    
    init() {
        if (!this.isSupported()) {
            console.warn('Performance tracking not supported in this browser');
            return;
        }
        
        this.setupObservers();
        
        if (this.options.enableAutoTracking) {
            this.trackPageLoad();
            this.trackCoreWebVitals();
        }
        
        if (this.options.enableNavigationTracking) {
            this.trackNavigation();
        }
        
        if (this.options.enableResourceTracking) {
            this.trackResources();
        }
        
        if (this.options.enableUserInteractionTracking) {
            this.trackUserInteractions();
        }
        
        // Track when page becomes visible/hidden
        this.trackVisibilityChanges();
        
        // Flush metrics before page unload
        this.setupBeforeUnload();
    }
    
    isSupported() {
        return 'performance' in window && 
               'PerformanceObserver' in window && 
               'requestIdleCallback' in window;
    }
    
    setupObservers() {
        // Performance Observer for various metrics
        if ('PerformanceObserver' in window) {
            try {
                // Navigation timing
                this.observer = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        this.processPerformanceEntry(entry);
                    }
                });
                
                // Observe different types of performance entries
                this.observer.observe({ 
                    entryTypes: ['navigation', 'paint', 'largest-contentful-paint', 'first-input', 'layout-shift', 'resource']
                });
            } catch (error) {
                console.warn('Performance Observer setup failed:', error);
            }
        }
    }
    
    processPerformanceEntry(entry) {
        const deviceInfo = this.getDeviceInfo();
        const timestamp = Date.now();
        
        switch (entry.entryType) {
            case 'navigation':
                this.trackMetric('page_load_time', entry.loadEventEnd - entry.loadEventStart, timestamp);
                this.trackMetric('dom_content_loaded', entry.domContentLoadedEventEnd - entry.domContentLoadedEventStart, timestamp);
                this.trackMetric('time_to_interactive', entry.loadEventEnd - entry.fetchStart, timestamp);
                break;
                
            case 'paint':
                if (entry.name === 'first-paint') {
                    this.trackMetric('first_paint', entry.startTime, timestamp);
                } else if (entry.name === 'first-contentful-paint') {
                    this.trackMetric('first_contentful_paint', entry.startTime, timestamp);
                }
                break;
                
            case 'largest-contentful-paint':
                this.trackMetric('largest_contentful_paint', entry.startTime, timestamp);
                break;
                
            case 'first-input':
                this.trackMetric('first_input_delay', entry.processingStart - entry.startTime, timestamp);
                break;
                
            case 'layout-shift':
                if (!entry.hadRecentInput) {
                    this.trackMetric('cumulative_layout_shift', entry.value, timestamp);
                }
                break;
                
            case 'resource':
                if (this.options.enableResourceTracking) {
                    this.trackResourceLoad(entry);
                }
                break;
        }
    }
    
    trackPageLoad() {
        // Track when page is fully loaded
        if (document.readyState === 'complete') {
            this.measurePageLoad();
        } else {
            window.addEventListener('load', () => this.measurePageLoad());
        }
    }
    
    measurePageLoad() {
        const navigation = performance.getEntriesByType('navigation')[0];
        if (navigation) {
            const metrics = {
                page_load_time: navigation.loadEventEnd - navigation.loadEventStart,
                dom_content_loaded: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
                dns_lookup: navigation.domainLookupEnd - navigation.domainLookupStart,
                tcp_connect: navigation.connectEnd - navigation.connectStart,
                server_response: navigation.responseEnd - navigation.requestStart,
                dom_parse: navigation.domInteractive - navigation.responseEnd,
                resource_load: navigation.loadEventStart - navigation.domContentLoadedEventEnd,
            };
            
            const timestamp = Date.now();
            Object.entries(metrics).forEach(([name, value]) => {
                if (value > 0) {
                    this.trackMetric(name, value, timestamp);
                }
            });
        }
    }
    
    trackCoreWebVitals() {
        // Track Core Web Vitals using web-vitals library pattern
        this.trackLCP();
        this.trackFID();
        this.trackCLS();
        this.trackTTFB();
    }
    
    trackLCP() {
        // Largest Contentful Paint
        if ('PerformanceObserver' in window) {
            try {
                let lcpValue = 0;
                const observer = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    lcpValue = lastEntry.startTime;
                    this.trackMetric('largest_contentful_paint', lcpValue, Date.now());
                });
                
                observer.observe({ entryTypes: ['largest-contentful-paint'] });
                
                // Stop observing after page is hidden
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden') {
                        observer.disconnect();
                        if (lcpValue > 0) {
                            this.trackMetric('largest_contentful_paint_final', lcpValue, Date.now());
                        }
                    }
                });
            } catch (error) {
                console.warn('LCP tracking failed:', error);
            }
        }
    }
    
    trackFID() {
        // First Input Delay
        if ('PerformanceObserver' in window) {
            try {
                const observer = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.processingStart > entry.startTime) {
                            const fid = entry.processingStart - entry.startTime;
                            this.trackMetric('first_input_delay', fid, Date.now());
                            observer.disconnect();
                            break;
                        }
                    }
                });
                
                observer.observe({ entryTypes: ['first-input'] });
            } catch (error) {
                console.warn('FID tracking failed:', error);
            }
        }
    }
    
    trackCLS() {
        // Cumulative Layout Shift
        if ('PerformanceObserver' in window) {
            try {
                let clsValue = 0;
                const observer = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (!entry.hadRecentInput) {
                            clsValue += entry.value;
                        }
                    }
                });
                
                observer.observe({ entryTypes: ['layout-shift'] });
                
                // Report final CLS when page is hidden
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden') {
                        observer.disconnect();
                        this.trackMetric('cumulative_layout_shift_final', clsValue, Date.now());
                    }
                });
            } catch (error) {
                console.warn('CLS tracking failed:', error);
            }
        }
    }
    
    trackTTFB() {
        // Time to First Byte
        const navigation = performance.getEntriesByType('navigation')[0];
        if (navigation) {
            const ttfb = navigation.responseStart - navigation.requestStart;
            this.trackMetric('time_to_first_byte', ttfb, Date.now());
        }
    }
    
    trackNavigation() {
        // Track single-page application navigation
        let currentPath = window.location.pathname;
        
        const trackNavigation = () => {
            const newPath = window.location.pathname;
            if (newPath !== currentPath) {
                const navigationTime = performance.now();
                this.trackMetric('spa_navigation_time', navigationTime, Date.now(), {
                    from: currentPath,
                    to: newPath
                });
                currentPath = newPath;
            }
        };
        
        // Listen for history changes
        window.addEventListener('popstate', trackNavigation);
        
        // Override pushState and replaceState
        const originalPushState = history.pushState;
        const originalReplaceState = history.replaceState;
        
        history.pushState = function(...args) {
            originalPushState.apply(this, args);
            setTimeout(trackNavigation, 0);
        };
        
        history.replaceState = function(...args) {
            originalReplaceState.apply(this, args);
            setTimeout(trackNavigation, 0);
        };
    }
    
    trackResources() {
        // Track resource loading performance
        const resourceTypes = ['script', 'stylesheet', 'image', 'font', 'fetch', 'xmlhttprequest'];
        
        if ('PerformanceObserver' in window) {
            try {
                const observer = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        this.trackResourceLoad(entry);
                    }
                });
                
                observer.observe({ entryTypes: ['resource'] });
            } catch (error) {
                console.warn('Resource tracking failed:', error);
            }
        }
    }
    
    trackResourceLoad(entry) {
        const resourceType = this.getResourceType(entry.name, entry.initiatorType);
        const loadTime = entry.responseEnd - entry.startTime;
        const size = entry.transferSize || entry.encodedBodySize || 0;
        
        this.trackMetric('resource_load_time', loadTime, Date.now(), {
            type: resourceType,
            url: entry.name,
            size: size,
            cached: entry.transferSize === 0 && entry.decodedBodySize > 0
        });
        
        // Track slow resources
        if (loadTime > 1000) { // Resources taking more than 1 second
            this.trackMetric('slow_resource', loadTime, Date.now(), {
                type: resourceType,
                url: entry.name,
                size: size
            });
        }
    }
    
    trackUserInteractions() {
        // Track user interaction responsiveness
        const interactionTypes = ['click', 'touchstart', 'keydown'];
        
        interactionTypes.forEach(type => {
            document.addEventListener(type, (event) => {
                const startTime = performance.now();
                
                requestAnimationFrame(() => {
                    const responseTime = performance.now() - startTime;
                    this.trackMetric('interaction_response_time', responseTime, Date.now(), {
                        type: type,
                        element: event.target.tagName || 'unknown'
                    });
                });
            }, { passive: true });
        });
        
        // Track scroll performance
        let scrollStartTime = 0;
        let isScrolling = false;
        
        document.addEventListener('scroll', () => {
            if (!isScrolling) {
                scrollStartTime = performance.now();
                isScrolling = true;
            }
            
            clearTimeout(this.scrollTimeout);
            this.scrollTimeout = setTimeout(() => {
                const scrollTime = performance.now() - scrollStartTime;
                this.trackMetric('scroll_performance', scrollTime, Date.now());
                isScrolling = false;
            }, 150);
        }, { passive: true });
    }
    
    trackVisibilityChanges() {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.trackMetric('page_hidden', performance.now(), Date.now());
                this.flush(); // Send any pending metrics
            } else {
                this.trackMetric('page_visible', performance.now(), Date.now());
            }
        });
    }
    
    setupBeforeUnload() {
        window.addEventListener('beforeunload', () => {
            this.flush(true); // Force synchronous send
        });
        
        // Use Page Visibility API as backup
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.flush();
            }
        });
    }
    
    trackMetric(name, value, timestamp = Date.now(), metadata = {}) {
        const metric = {
            name,
            value,
            timestamp,
            metadata: {
                ...metadata,
                page: window.location.pathname,
                user_agent: navigator.userAgent,
                connection: this.getConnectionInfo(),
                device: this.getDeviceInfo(),
                viewport: this.getViewportInfo()
            }
        };
        
        this.batch.push(metric);
        
        if (this.batch.length >= this.options.batchSize) {
            this.flush();
        } else {
            this.scheduleBatch();
        }
    }
    
    scheduleBatch() {
        if (this.batchTimeout) {
            clearTimeout(this.batchTimeout);
        }
        
        this.batchTimeout = setTimeout(() => {
            this.flush();
        }, this.options.batchTimeout);
    }
    
    flush(sync = false) {
        if (this.batch.length === 0) return;
        
        const metrics = [...this.batch];
        this.batch = [];
        
        if (this.batchTimeout) {
            clearTimeout(this.batchTimeout);
            this.batchTimeout = null;
        }
        
        const payload = {
            metrics,
            device_info: this.getDeviceInfo(),
            page_info: {
                url: window.location.href,
                path: window.location.pathname,
                referrer: document.referrer,
                title: document.title
            }
        };
        
        if (sync && 'sendBeacon' in navigator) {
            // Use sendBeacon for reliable delivery during page unload
            navigator.sendBeacon(
                this.options.endpoint,
                JSON.stringify(payload)
            );
        } else {
            // Use fetch for normal tracking
            fetch(this.options.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify(payload),
                keepalive: true
            }).catch(error => {
                console.warn('Performance tracking failed:', error);
                // Re-add metrics to batch for retry
                this.batch.unshift(...metrics);
            });
        }
    }
    
    getDeviceInfo() {
        return {
            type: this.getDeviceType(),
            screen_width: screen.width,
            screen_height: screen.height,
            device_pixel_ratio: window.devicePixelRatio || 1,
            touch_support: 'ontouchstart' in window,
            memory: navigator.deviceMemory || 'unknown',
            cores: navigator.hardwareConcurrency || 'unknown'
        };
    }
    
    getDeviceType() {
        const width = window.innerWidth;
        if (width < 768) return 'mobile';
        if (width < 1024) return 'tablet';
        return 'desktop';
    }
    
    getConnectionInfo() {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (connection) {
            return {
                type: connection.effectiveType || 'unknown',
                downlink: connection.downlink || 'unknown',
                rtt: connection.rtt || 'unknown',
                save_data: connection.saveData || false
            };
        }
        return { type: 'unknown' };
    }
    
    getViewportInfo() {
        return {
            width: window.innerWidth,
            height: window.innerHeight,
            orientation: screen.orientation ? screen.orientation.angle : 'unknown'
        };
    }
    
    getResourceType(url, initiatorType) {
        if (initiatorType) return initiatorType;
        
        // Determine type from URL
        if (url.match(/\.(js|mjs)(\?|$)/)) return 'script';
        if (url.match(/\.(css)(\?|$)/)) return 'stylesheet';
        if (url.match(/\.(jpg|jpeg|png|gif|webp|svg)(\?|$)/)) return 'image';
        if (url.match(/\.(woff|woff2|ttf|otf)(\?|$)/)) return 'font';
        if (url.includes('/api/')) return 'fetch';
        
        return 'other';
    }
    
    getCSRFToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }
    
    // Public API methods
    startMeasure(name) {
        performance.mark(`${name}_start`);
        return name;
    }
    
    endMeasure(name, metadata = {}) {
        performance.mark(`${name}_end`);
        performance.measure(name, `${name}_start`, `${name}_end`);
        
        const measure = performance.getEntriesByName(name, 'measure')[0];
        if (measure) {
            this.trackMetric(name, measure.duration, Date.now(), metadata);
        }
        
        // Clean up marks
        performance.clearMarks(`${name}_start`);
        performance.clearMarks(`${name}_end`);
        performance.clearMeasures(name);
        
        return measure ? measure.duration : 0;
    }
    
    trackCustomMetric(name, value, metadata = {}) {
        this.trackMetric(name, value, Date.now(), metadata);
    }
    
    trackError(error, metadata = {}) {
        this.trackMetric('javascript_error', 1, Date.now(), {
            message: error.message || 'Unknown error',
            stack: error.stack || 'No stack trace',
            filename: error.filename || 'Unknown file',
            line: error.lineno || 0,
            column: error.colno || 0,
            ...metadata
        });
    }
    
    trackApiCall(url, method, duration, status) {
        this.trackMetric('api_call_duration', duration, Date.now(), {
            url,
            method,
            status,
            type: 'api'
        });
        
        if (status >= 400) {
            this.trackMetric('api_error', 1, Date.now(), {
                url,
                method,
                status
            });
        }
    }
}

// Initialize performance tracking when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.performanceTracker = new PerformanceTracker({
        endpoint: '/api/v1/performance/track'
    });
    
    // Track JavaScript errors
    window.addEventListener('error', (event) => {
        window.performanceTracker.trackError(event.error || {
            message: event.message,
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno
        });
    });
    
    // Track unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
        window.performanceTracker.trackError({
            message: 'Unhandled promise rejection',
            reason: event.reason
        });
    });
});

// Export for module usage
export { PerformanceTracker };