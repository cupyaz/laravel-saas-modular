<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\MobileOptimization;

class MobileBladeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Mobile detection directives
        Blade::if('mobile', function () {
            return view()->getShared()['isMobile'] ?? false;
        });

        Blade::if('tablet', function () {
            return view()->getShared()['isTablet'] ?? false;
        });

        Blade::if('touch', function () {
            return view()->getShared()['isTouchDevice'] ?? false;
        });

        Blade::if('desktop', function () {
            $shared = view()->getShared();
            return !($shared['isMobile'] ?? false) && !($shared['isTablet'] ?? false);
        });

        // Device-specific viewport directive
        Blade::directive('viewport', function ($expression) {
            return "<?php echo '<meta name=\"viewport\" content=\"' . \App\Http\Middleware\MobileOptimization::getViewportMeta({$expression}) . '\">'; ?>";
        });

        // Device classes directive
        Blade::directive('deviceClasses', function () {
            return "<?php 
                \$shared = view()->getShared();
                echo \App\Http\Middleware\MobileOptimization::getDeviceClasses(
                    \$shared['isMobile'] ?? false, 
                    \$shared['isTablet'] ?? false
                ); 
            ?>";
        });

        // Mobile-optimized image directive
        Blade::directive('mobileImage', function ($expression) {
            return "<?php 
                \$args = {$expression};
                \$src = \$args['src'] ?? \$args[0] ?? '';
                \$alt = \$args['alt'] ?? \$args[1] ?? '';
                \$class = \$args['class'] ?? \$args[2] ?? '';
                \$shared = view()->getShared();
                \$deviceType = \$shared['deviceType'] ?? 'mobile';
                \$sizes = \App\Http\Middleware\MobileOptimization::getImageSizes(\$deviceType);
                echo '<img src=\"' . \$src . '\" alt=\"' . \$alt . '\" class=\"' . \$class . '\" loading=\"lazy\">';
            ?>";
        });

        // Progressive enhancement directive
        Blade::directive('progressive', function ($expression) {
            return "<?php \$shared = view()->getShared(); if(\$shared['isMobile'] ?? false): ?>";
        });

        Blade::directive('endprogressive', function () {
            return "<?php endif; ?>";
        });

        // Touch-optimized button directive
        Blade::directive('touchButton', function ($expression) {
            return "<?php 
                \$args = {$expression};
                \$text = \$args['text'] ?? \$args[0] ?? '';
                \$href = \$args['href'] ?? \$args[1] ?? '#';
                \$class = \$args['class'] ?? \$args[2] ?? 'btn';
                \$shared = view()->getShared();
                \$touchClass = (\$shared['isTouchDevice'] ?? false) ? ' touch-optimized' : '';
                echo '<a href=\"' . \$href . '\" class=\"' . \$class . \$touchClass . '\" onclick=\"\" role=\"button\">' . \$text . '</a>';
            ?>";
        });

        // Lazy loading directive
        Blade::directive('lazyLoad', function ($expression) {
            return "<?php 
                \$args = {$expression};
                \$src = \$args['src'] ?? \$args[0] ?? '';
                \$placeholder = \$args['placeholder'] ?? '/images/placeholder.svg';
                \$alt = \$args['alt'] ?? \$args[1] ?? '';
                \$class = \$args['class'] ?? \$args[2] ?? '';
                echo '<img src=\"' . \$placeholder . '\" data-src=\"' . \$src . '\" alt=\"' . \$alt . '\" class=\"lazy ' . \$class . '\">';
            ?>";
        });

        // Resource hints directive
        Blade::directive('resourceHints', function () {
            return "<?php 
                \$hints = app(\App\Services\PerformanceOptimizer::class)->getResourceHints(request());
                foreach (\$hints['preload'] as \$hint) {
                    echo '<link rel=\"preload\" href=\"' . \$hint['href'] . '\" as=\"' . \$hint['as'] . '\">' . PHP_EOL;
                }
                foreach (\$hints['prefetch'] as \$hint) {
                    echo '<link rel=\"prefetch\" href=\"' . \$hint['href'] . '\">' . PHP_EOL;
                }
                foreach (\$hints['preconnect'] as \$hint) {
                    echo '<link rel=\"preconnect\" href=\"' . \$hint['href'] . '\">' . PHP_EOL;
                }
            ?>";
        });

        // Critical CSS directive
        Blade::directive('criticalCSS', function ($expression) {
            return "<?php 
                \$isMobile = view()->shared('isMobile', false);
                \$criticalFile = \$isMobile ? 'critical-mobile.css' : 'critical-desktop.css';
                \$criticalPath = public_path('css/' . \$criticalFile);
                if (file_exists(\$criticalPath)) {
                    echo '<style>' . file_get_contents(\$criticalPath) . '</style>';
                }
            ?>";
        });

        // Performance tracking directive
        Blade::directive('trackPerformance', function ($expression) {
            return "<?php 
                \$metric = {$expression};
                echo '<script>
                    if (window.performance && window.performance.mark) {
                        window.performance.mark(\"' . \$metric . '_start\");
                        window.addEventListener(\"load\", function() {
                            window.performance.mark(\"' . \$metric . '_end\");
                            window.performance.measure(\"' . \$metric . '\", \"' . \$metric . '_start\", \"' . \$metric . '_end\");
                        });
                    }
                </script>';
            ?>";
        });

        // Service worker registration directive
        Blade::directive('serviceWorker', function () {
            return "<?php 
                echo '<script>
                    if (\"serviceWorker\" in navigator) {
                        window.addEventListener(\"load\", function() {
                            navigator.serviceWorker.register(\"/sw.js\")
                                .then(function(registration) {
                                    console.log(\"SW registered: \", registration);
                                }).catch(function(registrationError) {
                                    console.log(\"SW registration failed: \", registrationError);
                                });
                        });
                    }
                </script>';
            ?>";
        });

        // PWA install prompt directive
        Blade::directive('pwaInstall', function () {
            return "<?php 
                echo '<div id=\"pwa-install-prompt\" class=\"hidden fixed bottom-4 left-4 right-4 bg-primary-600 text-white p-4 rounded-lg shadow-lg z-50 md:left-auto md:right-4 md:w-80\">
                    <div class=\"flex items-center justify-between\">
                        <div class=\"flex-1\">
                            <h3 class=\"font-medium text-sm\">Install App</h3>
                            <p class=\"text-xs opacity-90 mt-1\">Add to home screen for better experience</p>
                        </div>
                        <div class=\"flex space-x-2 ml-4\">
                            <button id=\"pwa-install-dismiss\" class=\"text-xs underline opacity-75\">Later</button>
                            <button id=\"pwa-install-accept\" class=\"btn btn-sm bg-white text-primary-600 hover:bg-gray-100\">Install</button>
                        </div>
                    </div>
                </div>';
            ?>";
        });

        // Mobile-first grid directive
        Blade::directive('mobileGrid', function ($expression) {
            return "<?php 
                \$args = {$expression};
                \$cols = \$args['cols'] ?? 1;
                \$mdCols = \$args['md'] ?? 2;
                \$lgCols = \$args['lg'] ?? 3;
                \$gap = \$args['gap'] ?? 4;
                echo '<div class=\"grid grid-cols-' . \$cols . ' md:grid-cols-' . \$mdCols . ' lg:grid-cols-' . \$lgCols . ' gap-' . \$gap . '\">';
            ?>";
        });

        Blade::directive('endMobileGrid', function () {
            return "<?php echo '</div>'; ?>";
        });
    }
}