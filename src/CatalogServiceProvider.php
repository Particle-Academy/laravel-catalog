<?php

namespace LaravelCatalog;

use Illuminate\Support\ServiceProvider;

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

        // Cashier migrations are OPT-IN (default off). Auto-loading them
        // registers Cashier's `create_subscriptions_table` etc., which is
        // fatal for any host app that already owns a `subscriptions` table
        // (its own billing infra, not Cashier's). The host app is the right
        // place to decide whether Cashier owns those tables — enable
        // `catalog.load_cashier_migrations` for a greenfield Cashier app, or
        // `php artisan vendor:publish --tag=cashier-migrations` to manage
        // them yourself.
        if (config('catalog.load_cashier_migrations', false)) {
            $cashierReflection = new \ReflectionClass(\Laravel\Cashier\CashierServiceProvider::class);
            $cashierMigrationsPath = dirname($cashierReflection->getFileName(), 2).'/database/migrations';
            if (file_exists($cashierMigrationsPath)) {
                $this->loadMigrationsFrom($cashierMigrationsPath);
            }
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

        // Publish config
        $this->publishes([
            __DIR__.'/../config/catalog.php' => config_path('catalog.php'),
        ], 'catalog-config');
    }
}
