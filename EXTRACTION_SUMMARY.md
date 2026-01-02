# Laravel Catalog Package - Extraction Summary

This package contains all Catalog-related logic and views extracted from the main application.

## What Was Extracted

### Models
- `LaravelCatalog\Models\Product` - Product model with Stripe sync capabilities
- `LaravelCatalog\Models\Price` - Price model for recurring and one-time pricing
- `LaravelCatalog\Models\ProductFeature` - Product features for FMS integration

### Services
- `LaravelCatalog\Services\StripeCatalogService` - Syncs Products and Prices to Stripe
- `LaravelCatalog\Services\StripeCheckoutService` - Creates Stripe Checkout sessions

### Jobs
- `LaravelCatalog\Jobs\SyncProductToStripe` - Queued job to sync products to Stripe

### Events
- `LaravelCatalog\Events\ProductSyncedToStripe` - Broadcasts when product sync completes

### Migrations
- `create_products_table.php` - Products table
- `create_prices_table.php` - Prices table
- `create_product_features_table.php` - Product features table
- `create_product_feature_configs_table.php` - Pivot table for product-feature relationships

### Factories
- `ProductFactory` - Factory for Product model
- `PriceFactory` - Factory for Price model
- `ProductFeatureFactory` - Factory for ProductFeature model

### Seeders
- `ProductSeeder` - Seeds default products and prices

### Livewire Components & Views
The Livewire component (`Admin/Products/Index`) and its view have been updated for standalone package use:

1. **No Layout Dependency**: Removed app-specific layout dependencies (`x-slot`, `@teleport`)
2. **Custom CSS**: Uses custom CSS stylesheet instead of Flux UI (no external UI framework dependencies)
3. **Configurable Routes**: Route names are configurable via `config/catalog.php`
4. **Configurable Broadcasting**: Broadcasting channel is configurable via `config/catalog.php`

## Installation & Setup

1. Copy the package to your Laravel application
2. Register the service provider in `config/app.php` or use auto-discovery
3. Publish migrations: `php artisan vendor:publish --tag=catalog-migrations`
4. Run migrations: `php artisan migrate`
5. Publish views: `php artisan vendor:publish --tag=catalog-views`
6. Register routes (see `routes/web.php` example)
7. Configure Stripe credentials in `.env`

## Customization Required

### Layout
Update the `#[Layout]` attribute in the Livewire component to match your admin layout path.

### Routes
Register the admin routes in your `routes/web.php`:

```php
Route::prefix('ctrl')->name('admin.')->middleware(['web', 'auth', 'superadmin'])->group(function () {
    Route::get('/products', \LaravelCatalog\Livewire\Admin\Products\Index::class)->name('products.index');
});
```

### Broadcasting
Configure broadcasting channels in `routes/channels.php`:

```php
Broadcast::channel(config('catalog.broadcast_channel', 'admin.products'), function ($user) {
    return $user->isAdmin(); // Adjust based on your auth logic
});
```

### CSS Assets
The package includes a custom CSS stylesheet for the admin interface. After publishing assets, include it in your layout:

```blade
<link rel="stylesheet" href="{{ asset('vendor/catalog/admin.css') }}">
```

### Dependencies
- Laravel Cashier ^15.0 (for Stripe integration)
- Stripe PHP SDK ^13.0 or ^16.0
- Livewire 3+
- PHP 8.2+

## Package Structure

```
laravel-catalog/
├── src/
│   ├── Models/
│   │   ├── Product.php
│   │   ├── Price.php
│   │   └── ProductFeature.php
│   ├── Services/
│   │   ├── StripeCatalogService.php
│   │   └── StripeCheckoutService.php
│   ├── Livewire/
│   │   └── Admin/
│   │       └── Products/
│   │           └── Index.php
│   ├── Jobs/
│   │   └── SyncProductToStripe.php
│   ├── Events/
│   │   └── ProductSyncedToStripe.php
│   └── CatalogServiceProvider.php
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── resources/
│   └── views/
│       └── livewire/
│           └── admin/
│               └── products/
│                   └── index.blade.php
├── config/
│   └── catalog.php
└── composer.json
```

