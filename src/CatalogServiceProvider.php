<?php

namespace LaravelCatalog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/catalog.php',
            'catalog'
        );

        // Register services as singletons
        $this->app->singleton(\LaravelCatalog\Services\StripeCatalogService::class);
        $this->app->singleton(\LaravelCatalog\Services\StripeCheckoutService::class);

        // Register CatalogManager and bind to facade
        $this->app->singleton('catalog', function ($app) {
            return new \LaravelCatalog\CatalogManager(
                $app->make(\LaravelCatalog\Services\StripeCatalogService::class),
                $app->make(\LaravelCatalog\Services\StripeCheckoutService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configure FMS to use Catalog's ProductFeature model
        config(['fms.product_feature_model' => \LaravelCatalog\Models\ProductFeature::class]);

        // Load Cashier migrations (Catalog depends on Cashier)
        // Why: Since Catalog requires Cashier, we auto-load Cashier's migrations
        // so users don't need to manually publish them
        $cashierReflection = new \ReflectionClass(\Laravel\Cashier\CashierServiceProvider::class);
        $cashierMigrationsPath = dirname($cashierReflection->getFileName(), 2).'/database/migrations';
        if (file_exists($cashierMigrationsPath)) {
            $this->loadMigrationsFrom($cashierMigrationsPath);
        }

        // Load migrations from package
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load factories from package
        if (method_exists($this, 'loadFactoriesFrom')) {
            $this->loadFactoriesFrom(__DIR__.'/../database/factories');
        }

        // Publish migrations (optional - for customization)
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'catalog-migrations');

        // Publish factories
        $this->publishes([
            __DIR__.'/../database/factories' => database_path('factories'),
        ], 'catalog-factories');

        // Publish seeders
        $this->publishes([
            __DIR__.'/../database/seeders' => database_path('seeders'),
        ], 'catalog-seeders');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/catalog'),
        ], 'catalog-views');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/catalog.php' => config_path('catalog.php'),
        ], 'catalog-config');

        // Publish CSS
        $this->publishes([
            __DIR__.'/../resources/css/admin.css' => public_path('vendor/catalog/admin.css'),
        ], 'catalog-assets');

        // Only load UI components if enabled
        if ($this->shouldLoadUi()) {
            // Load views
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'catalog');

            // Register Livewire component alias so AJAX requests can resolve it
            Livewire::component('laravel-catalog.livewire.admin.products', \LaravelCatalog\Livewire\Admin\Products\Index::class);
        }
    }

    /**
     * Determine if UI components should be loaded.
     * UI is enabled if:
     * - enable_ui config is true, OR
     * - views have been published (views exist in vendor/catalog)
     */
    protected function shouldLoadUi(): bool
    {
        // Check if UI is explicitly enabled in config
        if (config('catalog.enable_ui', false)) {
            return true;
        }

        // Check if views have been published
        $publishedViewsPath = resource_path('views/vendor/catalog');
        if (file_exists($publishedViewsPath) && is_dir($publishedViewsPath)) {
            return true;
        }

        return false;
    }
}

