# Laravel Catalog Package - Extraction Summary

This package contains all Catalog-related logic extracted from the main application.

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

## Installation & Setup

1. `composer require particle-academy/laravel-catalog`
2. Service provider auto-discovers
3. Publish migrations: `php artisan vendor:publish --tag=catalog-migrations`
4. Run migrations: `php artisan migrate`
5. Configure Stripe credentials in `.env`

## Customization

### Broadcasting

Configure broadcasting channels in `routes/channels.php`:

```php
Broadcast::channel(config('catalog.broadcast_channel', 'admin.products'), function ($user) {
    return $user->isAdmin();
});
```

### Dependencies

- Laravel Framework ^11.0 | ^12.0 | ^13.0
- Laravel Cashier ^15.0 | ^16.0 (for Stripe integration)
- Stripe PHP SDK ^13.0 | ^16.0 | ^17.0
- Particle Academy FMS ^0.2 .. ^0.5
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
│   ├── Jobs/
│   │   └── SyncProductToStripe.php
│   ├── Events/
│   │   └── ProductSyncedToStripe.php
│   ├── Facades/
│   │   └── Catalog.php
│   ├── CatalogManager.php
│   └── CatalogServiceProvider.php
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── config/
│   └── catalog.php
└── composer.json
```
