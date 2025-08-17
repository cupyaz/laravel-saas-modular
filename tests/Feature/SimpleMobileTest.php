<?php

namespace Tests\Feature;

use Tests\TestCase;

class SimpleMobileTest extends TestCase
{
    public function test_pwa_manifest_is_accessible()
    {
        // Test file exists
        $this->assertFileExists(public_path('manifest.json'));
        
        // Test content is valid JSON
        $content = file_get_contents(public_path('manifest.json'));
        $manifest = json_decode($content, true);
        
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('short_name', $manifest);
        $this->assertArrayHasKey('start_url', $manifest);
        $this->assertArrayHasKey('display', $manifest);
        $this->assertArrayHasKey('icons', $manifest);
        $this->assertEquals('standalone', $manifest['display']);
    }

    public function test_service_worker_is_accessible()
    {
        // Test file exists
        $this->assertFileExists(public_path('sw.js'));
        
        // Test content is valid JavaScript
        $content = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('Service Worker', $content);
        $this->assertStringContainsString('CACHE_NAME', $content);
    }

    public function test_offline_page_is_accessible()
    {
        // Test file exists
        $this->assertFileExists(public_path('offline.html'));
        
        // Test content has required elements
        $content = file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString('You\'re currently offline', $content);
        $this->assertStringContainsString('Try Again', $content);
    }

    public function test_critical_css_files_exist()
    {
        $this->assertFileExists(public_path('css/critical-mobile.css'));
        $this->assertFileExists(public_path('css/critical-desktop.css'));

        $mobileCss = file_get_contents(public_path('css/critical-mobile.css'));
        $desktopCss = file_get_contents(public_path('css/critical-desktop.css'));

        // Check for mobile-first styles
        $this->assertStringContainsString('min-height:44px', $mobileCss);
        $this->assertStringContainsString('touch-action:manipulation', $mobileCss);
        $this->assertStringContainsString('safe-area-inset', $mobileCss);

        // Check for desktop optimizations
        $this->assertStringContainsString('hover\\:transform', $desktopCss);
        $this->assertStringContainsString('sidebar', $desktopCss);
    }

    public function test_mobile_javascript_files_exist()
    {
        $this->assertFileExists(public_path('js/mobile-navigation.js'));
        $this->assertFileExists(public_path('js/touch-gestures.js'));
        $this->assertFileExists(public_path('js/pwa.js'));
        $this->assertFileExists(resource_path('js/performance-tracker.js'));
    }

    public function test_performance_budget_compliance()
    {
        // Check that critical CSS files are under size limits
        $mobileCssSize = filesize(public_path('css/critical-mobile.css'));
        $desktopCssSize = filesize(public_path('css/critical-desktop.css'));
        
        // Critical CSS should be under 14KB (recommended limit)
        $this->assertLessThan(14 * 1024, $mobileCssSize, 'Mobile critical CSS exceeds 14KB limit');
        $this->assertLessThan(14 * 1024, $desktopCssSize, 'Desktop critical CSS exceeds 14KB limit');
    }

    public function test_mobile_optimization_middleware_class_methods()
    {
        // Test viewport meta generation
        $mobileViewport = \App\Http\Middleware\MobileOptimization::getViewportMeta('mobile');
        $tabletViewport = \App\Http\Middleware\MobileOptimization::getViewportMeta('tablet');
        $desktopViewport = \App\Http\Middleware\MobileOptimization::getViewportMeta('desktop');

        $this->assertStringContainsString('width=device-width', $mobileViewport);
        $this->assertStringContainsString('initial-scale=1', $mobileViewport);
        $this->assertStringContainsString('viewport-fit=cover', $mobileViewport);

        // Test device classes
        $mobileClasses = \App\Http\Middleware\MobileOptimization::getDeviceClasses(true, false);
        $tabletClasses = \App\Http\Middleware\MobileOptimization::getDeviceClasses(false, true);
        $desktopClasses = \App\Http\Middleware\MobileOptimization::getDeviceClasses(false, false);

        $this->assertStringContainsString('is-mobile', $mobileClasses);
        $this->assertStringContainsString('is-touch', $mobileClasses);
        $this->assertStringContainsString('is-tablet', $tabletClasses);
        $this->assertStringContainsString('is-desktop', $desktopClasses);

        // Test image sizes
        $mobileSizes = \App\Http\Middleware\MobileOptimization::getImageSizes('mobile');
        $this->assertArrayHasKey('thumbnail', $mobileSizes);
        $this->assertEquals('150x150', $mobileSizes['thumbnail']);
    }

    public function test_touch_gesture_implementation()
    {
        $gestureJs = file_get_contents(public_path('js/touch-gestures.js'));
        
        // Check for touch event handlers
        $this->assertStringContainsString('touchstart', $gestureJs);
        $this->assertStringContainsString('touchmove', $gestureJs);
        $this->assertStringContainsString('touchend', $gestureJs);
        
        // Check for gesture recognition
        $this->assertStringContainsString('swipe', $gestureJs);
        $this->assertStringContainsString('pinch', $gestureJs);
        $this->assertStringContainsString('tap', $gestureJs);
    }

    public function test_pwa_functionality()
    {
        $pwaJs = file_get_contents(public_path('js/pwa.js'));
        
        // Check for install prompt handling
        $this->assertStringContainsString('beforeinstallprompt', $pwaJs);
        $this->assertStringContainsString('appinstalled', $pwaJs);
        $this->assertStringContainsString('showInstallButton', $pwaJs);
        
        // Check for iOS Safari standalone detection
        $this->assertStringContainsString('navigator.standalone', $pwaJs);
    }

    public function test_service_worker_implementation()
    {
        $swContent = file_get_contents(public_path('sw.js'));
        
        // Check for offline cache strategies
        $this->assertStringContainsString('CACHE_NAME', $swContent);
        $this->assertStringContainsString('caches.open', $swContent);
        $this->assertStringContainsString('fetch', $swContent);
        
        // Check for offline page handling
        $this->assertStringContainsString('/offline.html', $swContent);
    }

    public function test_performance_tracking_implementation()
    {
        $performanceJs = file_get_contents(resource_path('js/performance-tracker.js'));
        
        // Check for Core Web Vitals tracking
        $this->assertStringContainsString('largest_contentful_paint', $performanceJs);
        $this->assertStringContainsString('first_input_delay', $performanceJs);
        $this->assertStringContainsString('cumulative_layout_shift', $performanceJs);
        
        // Check for mobile-specific tracking
        $this->assertStringContainsString('getDeviceType', $performanceJs);
        $this->assertStringContainsString('getConnectionInfo', $performanceJs);
    }
}