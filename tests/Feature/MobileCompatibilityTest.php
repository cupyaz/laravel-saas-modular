<?php

namespace Tests\Feature;

use App\Http\Middleware\MobileOptimization;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MobileCompatibilityTest extends TestCase
{
    use WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // $this->user = User::factory()->create();
    }

    /** @test */
    public function mobile_middleware_detects_mobile_devices()
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15'
        ])->get('/dashboard');

        $response->assertStatus(200);
        $response->assertHeader('X-Device-Type', 'mobile');
    }

    /** @test */
    public function mobile_middleware_detects_tablet_devices()
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X) AppleWebKit/605.1.15'
        ])->get('/dashboard');

        $response->assertStatus(200);
        $response->assertHeader('X-Device-Type', 'tablet');
    }

    /** @test */
    public function mobile_middleware_detects_desktop_devices()
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])->get('/dashboard');

        $response->assertStatus(200);
        $response->assertHeader('X-Device-Type', 'desktop');
    }

    /** @test */
    public function mobile_devices_receive_mobile_optimized_headers()
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15'
        ])->get('/dashboard');

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');
        $response->assertHeader('Save-Data', 'on');
        $response->assertHeader('Touch-Action', 'manipulation');
    }

    /** @test */
    public function mobile_viewport_meta_tag_generation()
    {
        $mobileViewport = MobileOptimization::getViewportMeta('mobile');
        $tabletViewport = MobileOptimization::getViewportMeta('tablet');
        $desktopViewport = MobileOptimization::getViewportMeta('desktop');

        $this->assertStringContainsString('width=device-width', $mobileViewport);
        $this->assertStringContainsString('initial-scale=1', $mobileViewport);
        $this->assertStringContainsString('viewport-fit=cover', $mobileViewport);

        $this->assertStringContainsString('width=device-width', $tabletViewport);
        $this->assertStringContainsString('maximum-scale=3', $tabletViewport);

        $this->assertStringContainsString('width=device-width', $desktopViewport);
        $this->assertStringNotContainsString('viewport-fit=cover', $desktopViewport);
    }

    /** @test */
    public function device_specific_css_classes()
    {
        $mobileClasses = MobileOptimization::getDeviceClasses(true, false);
        $tabletClasses = MobileOptimization::getDeviceClasses(false, true);
        $desktopClasses = MobileOptimization::getDeviceClasses(false, false);

        $this->assertStringContainsString('is-mobile', $mobileClasses);
        $this->assertStringContainsString('is-touch', $mobileClasses);

        $this->assertStringContainsString('is-tablet', $tabletClasses);
        $this->assertStringContainsString('is-touch', $tabletClasses);

        $this->assertStringContainsString('is-desktop', $desktopClasses);
        $this->assertStringContainsString('is-no-touch', $desktopClasses);
    }

    /** @test */
    public function mobile_optimized_image_sizes()
    {
        $mobileSizes = MobileOptimization::getImageSizes('mobile');
        $tabletSizes = MobileOptimization::getImageSizes('tablet');
        $desktopSizes = MobileOptimization::getImageSizes('desktop');

        $this->assertArrayHasKey('thumbnail', $mobileSizes);
        $this->assertArrayHasKey('medium', $mobileSizes);
        $this->assertEquals('150x150', $mobileSizes['thumbnail']);

        $this->assertEquals('200x200', $tabletSizes['thumbnail']);
        $this->assertEquals('250x250', $desktopSizes['thumbnail']);
    }

    /** @test */
    public function pwa_manifest_is_accessible()
    {
        $response = $this->get('/manifest.json');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        
        $manifest = $response->json();
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('short_name', $manifest);
        $this->assertArrayHasKey('start_url', $manifest);
        $this->assertArrayHasKey('display', $manifest);
        $this->assertArrayHasKey('icons', $manifest);
        $this->assertEquals('standalone', $manifest['display']);
    }

    /** @test */
    public function service_worker_is_accessible()
    {
        $response = $this->get('/sw.js');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/javascript');
    }

    /** @test */
    public function offline_page_is_accessible()
    {
        $response = $this->get('/offline.html');
        
        $response->assertStatus(200);
        $response->assertSee('You\'re currently offline');
        $response->assertSee('Try Again');
    }

    /** @test */
    public function critical_css_files_exist()
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

    /** @test */
    public function mobile_navigation_javascript_exists()
    {
        $this->assertFileExists(public_path('js/mobile-navigation.js'));
        $this->assertFileExists(public_path('js/touch-gestures.js'));
        $this->assertFileExists(public_path('js/pwa.js'));
        $this->assertFileExists(resource_path('js/performance-tracker.js'));
    }

    /** @test */
    public function accessibility_requirements_met()
    {
        // Test semantic HTML structure
        $response = $this->actingAs($this->user)->get('/dashboard');
        
        $response->assertStatus(200);
        
        // Check for proper semantic elements
        $response->assertSee('<main', false);
        $response->assertSee('<nav', false);
        $response->assertSee('<header', false);
        
        // Check for proper heading hierarchy
        $response->assertSee('<h1', false);
        
        // Check for skip links (accessibility)
        $response->assertSee('Skip to main content', false);
        
        // Check for proper form labels
        $content = $response->getContent();
        $this->assertRegExp('/<label[^>]*for=["\'][^"\']+["\']/', $content);
    }

    /** @test */
    public function touch_targets_meet_minimum_size()
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        
        $response->assertStatus(200);
        
        // Verify CSS includes proper touch target sizes
        $css = file_get_contents(public_path('css/critical-mobile.css'));
        $this->assertStringContainsString('min-height:44px', $css);
        $this->assertStringContainsString('min-width:44px', $css);
    }

    /** @test */
    public function keyboard_navigation_support()
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        
        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Check for proper tabindex usage
        $this->assertRegExp('/tabindex=["\'][0-9-]+["\']/', $content);
        
        // Check for focus styles in CSS
        $css = file_get_contents(public_path('css/critical-mobile.css'));
        $this->assertStringContainsString(':focus', $css);
        $this->assertStringContainsString('outline', $css);
    }

    /** @test */
    public function screen_reader_compatibility()
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        
        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Check for ARIA attributes
        $this->assertRegExp('/aria-[a-z]+=["\'][^"\']*["\']/', $content);
        
        // Check for alt text on images
        $this->assertRegExp('/<img[^>]+alt=["\'][^"\']*["\']/', $content);
        
        // Check for proper role attributes
        $this->assertRegExp('/role=["\'][^"\']+["\']/', $content);
    }

    /** @test */
    public function color_contrast_meets_wcag_standards()
    {
        // This would typically be done with automated tools like axe-core
        // For now, we'll check that proper contrast classes are used
        $css = file_get_contents(public_path('css/critical-mobile.css'));
        
        // Verify high contrast color combinations
        $this->assertStringContainsString('text-gray-900', $css);
        $this->assertStringContainsString('bg-white', $css);
        $this->assertStringContainsString('text-white', $css);
        $this->assertStringContainsString('bg-primary-600', $css);
    }

    /** @test */
    public function responsive_breakpoints_work_correctly()
    {
        $css = file_get_contents(public_path('css/critical-mobile.css'));
        
        // Check for mobile-first media queries
        $this->assertStringContainsString('@media (min-width:640px)', $css);
        $this->assertStringContainsString('@media (min-width:768px)', $css);
        
        // Check for proper responsive utilities
        $this->assertStringContainsString('md:grid-cols-', $css);
        $this->assertStringContainsString('lg:grid-cols-', $css);
    }

    /** @test */
    public function performance_budget_compliance()
    {
        // Check that critical CSS files are under size limits
        $mobileCssSize = filesize(public_path('css/critical-mobile.css'));
        $desktopCssSize = filesize(public_path('css/critical-desktop.css'));
        
        // Critical CSS should be under 14KB (recommended limit)
        $this->assertLessThan(14 * 1024, $mobileCssSize, 'Mobile critical CSS exceeds 14KB limit');
        $this->assertLessThan(14 * 1024, $desktopCssSize, 'Desktop critical CSS exceeds 14KB limit');
    }

    /** @test */
    public function lazy_loading_implementation()
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        
        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Check for lazy loading attributes
        $this->assertRegExp('/loading=["\']lazy["\']/', $content);
        $this->assertRegExp('/data-src=["\'][^"\']+["\']/', $content);
    }

    /** @test */
    public function error_handling_for_poor_connections()
    {
        // Test with Save-Data header
        $response = $this->withHeaders([
            'Save-Data' => 'on',
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)'
        ])->actingAs($this->user)->get('/dashboard');
        
        $response->assertStatus(200);
        $response->assertHeader('Save-Data', 'on');
    }

    /** @test */
    public function mobile_form_usability()
    {
        $response = $this->actingAs($this->user)->get('/profile');
        
        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Check for proper input types
        $this->assertRegExp('/type=["\']email["\']/', $content);
        $this->assertRegExp('/type=["\']tel["\']/', $content);
        
        // Check for autocomplete attributes
        $this->assertRegExp('/autocomplete=["\'][^"\']+["\']/', $content);
        
        // Check for proper form validation
        $this->assertRegExp('/required/', $content);
    }

    /** @test */
    public function touch_gesture_support_implemented()
    {
        $this->assertFileExists(public_path('js/touch-gestures.js'));
        
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

    /** @test */
    public function offline_functionality_works()
    {
        // Test service worker registration
        $response = $this->get('/sw.js');
        $response->assertStatus(200);
        
        $swContent = $response->getContent();
        
        // Check for offline cache strategies
        $this->assertStringContainsString('CACHE_NAME', $swContent);
        $this->assertStringContainsString('caches.open', $swContent);
        $this->assertStringContainsString('fetch', $swContent);
        
        // Check for offline page handling
        $this->assertStringContainsString('/offline.html', $swContent);
    }

    /** @test */
    public function pwa_installation_prompts_work()
    {
        $pwaJs = file_get_contents(public_path('js/pwa.js'));
        
        // Check for install prompt handling
        $this->assertStringContainsString('beforeinstallprompt', $pwaJs);
        $this->assertStringContainsString('appinstalled', $pwaJs);
        $this->assertStringContainsString('showInstallButton', $pwaJs);
        
        // Check for iOS Safari standalone detection
        $this->assertStringContainsString('navigator.standalone', $pwaJs);
    }
}