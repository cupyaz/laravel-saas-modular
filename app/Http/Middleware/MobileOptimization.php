<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MobileOptimization
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Detect mobile device
        $isMobile = $this->isMobileDevice($request);
        $isTablet = $this->isTabletDevice($request);
        $isTouchDevice = $isMobile || $isTablet;
        
        // Add device detection to view data
        if (method_exists($response, 'header')) {
            $response->header('X-Device-Type', $isMobile ? 'mobile' : ($isTablet ? 'tablet' : 'desktop'));
        }
        
        // Share device info with views
        view()->share('isMobile', $isMobile);
        view()->share('isTablet', $isTablet);
        view()->share('isTouchDevice', $isTouchDevice);
        view()->share('deviceType', $isMobile ? 'mobile' : ($isTablet ? 'tablet' : 'desktop'));
        
        // Add mobile-specific headers
        if ($isMobile) {
            $this->addMobileHeaders($response);
        }
        
        // Add performance headers
        $this->addPerformanceHeaders($response);
        
        return $response;
    }
    
    /**
     * Detect if the request is from a mobile device
     */
    private function isMobileDevice(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');
        
        // Mobile patterns
        $mobilePatterns = [
            '/Mobile/i',
            '/Android/i',
            '/iPhone/i',
            '/iPod/i',
            '/BlackBerry/i',
            '/Windows Phone/i',
            '/webOS/i',
            '/Opera Mini/i',
            '/IEMobile/i',
            '/Mobile.*Firefox/i',
        ];
        
        foreach ($mobilePatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect if the request is from a tablet device
     */
    private function isTabletDevice(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');
        
        // Tablet patterns
        $tabletPatterns = [
            '/iPad/i',
            '/Android.*Tablet/i',
            '/Android(?!.*Mobile)/i',
            '/Kindle/i',
            '/Silk/i',
            '/PlayBook/i',
            '/Tablet/i',
        ];
        
        foreach ($tabletPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add mobile-specific headers
     */
    private function addMobileHeaders(Response $response): void
    {
        // Optimize for mobile networks
        $response->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');
        
        // Reduce data usage
        $response->header('Save-Data', 'on');
        
        // Mobile-specific content hints
        $response->header('Vary', 'User-Agent, Save-Data');
        
        // Touch optimization
        $response->header('Touch-Action', 'manipulation');
    }
    
    /**
     * Add performance optimization headers
     */
    private function addPerformanceHeaders(Response $response): void
    {
        // Resource hints for critical resources
        $response->header('Link', '</css/app.css>; rel=preload; as=style, </js/app.js>; rel=preload; as=script');
        
        // Connection optimization
        $response->header('Connection', 'keep-alive');
        
        // Compression hints
        if (!$response->headers->has('Content-Encoding')) {
            $response->header('Vary', $response->headers->get('Vary', '') . ', Accept-Encoding');
        }
    }
    
    /**
     * Get device-specific viewport meta tag
     */
    public static function getViewportMeta(string $deviceType = 'mobile'): string
    {
        $viewports = [
            'mobile' => 'width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover',
            'tablet' => 'width=device-width, initial-scale=1, maximum-scale=3, user-scalable=yes',
            'desktop' => 'width=device-width, initial-scale=1',
        ];
        
        return $viewports[$deviceType] ?? $viewports['mobile'];
    }
    
    /**
     * Get device-specific CSS classes
     */
    public static function getDeviceClasses(bool $isMobile, bool $isTablet): string
    {
        $classes = [];
        
        if ($isMobile) {
            $classes[] = 'is-mobile';
        } elseif ($isTablet) {
            $classes[] = 'is-tablet';
        } else {
            $classes[] = 'is-desktop';
        }
        
        if ($isMobile || $isTablet) {
            $classes[] = 'is-touch';
        } else {
            $classes[] = 'is-no-touch';
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Get optimized image sizes for device
     */
    public static function getImageSizes(string $deviceType = 'mobile'): array
    {
        $sizes = [
            'mobile' => [
                'thumbnail' => '150x150',
                'small' => '300x200',
                'medium' => '600x400',
                'large' => '900x600',
            ],
            'tablet' => [
                'thumbnail' => '200x200',
                'small' => '400x300',
                'medium' => '800x600',
                'large' => '1200x800',
            ],
            'desktop' => [
                'thumbnail' => '250x250',
                'small' => '500x400',
                'medium' => '1000x700',
                'large' => '1600x1000',
            ],
        ];
        
        return $sizes[$deviceType] ?? $sizes['mobile'];
    }
}