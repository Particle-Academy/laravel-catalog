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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {

        // Load migrations from package
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

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

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'catalog');

        // Register Livewire component alias so AJAX requests can resolve it
        Livewire::component('laravel-catalog.livewire.admin.products', \LaravelCatalog\Livewire\Admin\Products\Index::class);
    }
}

