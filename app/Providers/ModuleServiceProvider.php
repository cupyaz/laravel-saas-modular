<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerModules();
    }

    public function boot(): void
    {
        $this->bootModules();
    }

    protected function registerModules(): void
    {
        $modulePath = base_path('modules');
        
        if (!is_dir($modulePath)) {
            return;
        }

        $modules = glob($modulePath . '/*', GLOB_ONLYDIR);

        foreach ($modules as $module) {
            $moduleName = basename($module);
            $providerPath = $module . '/Providers/' . $moduleName . 'ServiceProvider.php';
            
            if (file_exists($providerPath)) {
                $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
                
                if (class_exists($providerClass)) {
                    $this->app->register($providerClass);
                }
            }
        }
    }

    protected function bootModules(): void
    {
        $modulePath = base_path('modules');
        
        if (!is_dir($modulePath)) {
            return;
        }

        $modules = glob($modulePath . '/*', GLOB_ONLYDIR);

        foreach ($modules as $module) {
            $moduleName = basename($module);
            
            // Load routes
            $webRoutes = $module . '/routes/web.php';
            $apiRoutes = $module . '/routes/api.php';

            if (file_exists($webRoutes)) {
                $this->loadRoutesFrom($webRoutes);
            }

            if (file_exists($apiRoutes)) {
                $this->loadRoutesFrom($apiRoutes);
            }

            // Load views
            $viewsPath = $module . '/resources/views';
            if (is_dir($viewsPath)) {
                $this->loadViewsFrom($viewsPath, strtolower($moduleName));
            }

            // Load migrations
            $migrationsPath = $module . '/database/migrations';
            if (is_dir($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }
}