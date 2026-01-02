# Laravel Catalog Package

A Laravel package for managing Stripe catalog (Products and Prices) with a complete admin UI.

## Features

- **Product Management**: Create, edit, and manage Stripe products
- **Price Management**: Manage recurring and one-time prices for products
- **Stripe Sync**: Automatic synchronization with Stripe's catalog
- **Admin UI**: Complete Livewire-based admin interface
- **Product Features**: Support for product features and feature configurations
- **Checkout Integration**: Ready-to-use Stripe Checkout session creation

## Requirements

- Laravel 11+ or 12+
- PHP 8.2+
- Laravel Cashier ^15.0
- Stripe PHP SDK ^13.0 or ^16.0
- Livewire 3+

## Installation

### Step 1: Install via Composer

```bash
composer require particle-academy/laravel-catalog
```

The package will auto-discover and register its service provider.

### Step 2: Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=catalog-config
```

This creates `config/catalog.php` where you can customize:
- Auto-sync to Stripe
- Queue connection for sync jobs
- Admin route prefix and middleware
- Broadcasting channel

### Step 3: Publish and Run Migrations

**Important**: The package requires Laravel Cashier migrations to be published since it uses Cashier for subscription checkout.

```bash
# Publish Cashier migrations (required)
php artisan vendor:publish --tag="cashier-migrations"

# Run all migrations (package migrations are auto-loaded)
php artisan migrate
```

The package includes these migrations:
- `create_products_table` - Products table with Stripe sync fields
- `create_prices_table` - Prices table for recurring and one-time pricing
- `create_product_features_table` - Product features table
- `create_product_feature_configs_table` - Product-feature pivot table

**Note**: Cashier migrations (subscriptions, subscription_items, customer columns) are also required and must be published separately.

### Step 4: Publish Assets (Optional)

Publish CSS assets for the admin interface:

```bash
php artisan vendor:publish --tag=catalog-assets
```

Then include the CSS in your layout:

```blade
<link rel="stylesheet" href="{{ asset('vendor/catalog/admin.css') }}">
```

### Step 5: Register Admin Routes

Add the admin routes to your `routes/web.php`:

```php
use LaravelCatalog\Livewire\Admin\Products\Index as ProductsIndex;

Route::prefix('ctrl')->name('admin.')->middleware(config('catalog.admin_middleware'))->group(function () {
    Route::get('/products', ProductsIndex::class)->name('products.index');
});
```

The middleware defaults to `['web', 'auth']` but can be customized in `config/catalog.php`.

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Stripe Configuration (via Laravel Cashier)
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret

# Catalog Package Configuration
CATALOG_AUTO_SYNC_STRIPE=false
CATALOG_QUEUE_CONNECTION=default
CATALOG_ADMIN_PREFIX=ctrl
```

### Configuration File

After publishing, edit `config/catalog.php`:

```php
return [
    // Auto-sync products/prices to Stripe when created/updated
    'auto_sync_stripe' => env('CATALOG_AUTO_SYNC_STRIPE', false),

    // Queue connection for sync jobs
    'queue_connection' => env('CATALOG_QUEUE_CONNECTION', 'default'),

    // Admin route prefix
    'admin_route_prefix' => env('CATALOG_ADMIN_PREFIX', 'ctrl'),

    // Admin route middleware
    'admin_middleware' => ['web', 'auth'],

    // Broadcasting channel for sync events
    'broadcast_channel' => 'admin.products',
];
```

## Usage

### Creating Products

```php
use LaravelCatalog\Models\Product;

$product = Product::factory()->create([
    'name' => 'Pro Plan',
    'description' => 'Perfect for growing teams',
    'active' => true,
    'metadata' => [
        'storefront' => [
            'plan' => [
                'show' => true,
                'recommended' => true,
            ],
        ],
    ],
]);
```

### Creating Prices

```php
use LaravelCatalog\Models\Price;

// Recurring monthly price
$monthlyPrice = Price::factory()
    ->for($product)
    ->create([
        'unit_amount' => 2900, // $29.00 in cents
        'currency' => 'USD',
        'recurring_interval' => 'month',
        'type' => Price::TYPE_RECURRING,
    ]);

// One-time price
$oneTimePrice = Price::factory()
    ->for($product)
    ->oneTime()
    ->create([
        'unit_amount' => 9900, // $99.00 in cents
        'currency' => 'USD',
    ]);
```

### Syncing to Stripe

#### Manual Sync

```php
use LaravelCatalog\Jobs\SyncProductToStripe;

// Dispatch sync job
SyncProductToStripe::dispatch($product->id);

// Or sync directly
use LaravelCatalog\Services\StripeCatalogService;

$catalogService = app(StripeCatalogService::class);
$catalogService->syncProduct($product);
```

#### Auto Sync

Enable auto-sync in `config/catalog.php`:

```php
'auto_sync_stripe' => true,
```

Products and prices will automatically sync to Stripe when created or updated.

### Creating Checkout Sessions

#### Subscription Checkout

```php
use LaravelCatalog\Services\StripeCheckoutService;
use LaravelCatalog\Models\Price;
use App\Models\User;

$checkoutService = app(StripeCheckoutService::class);
$user = User::find(1);
$price = Price::find($priceId);

// Ensure price has been synced to Stripe first
if (!$price->stripePriceId()) {
    SyncProductToStripe::dispatch($price->product_id);
    // Wait for sync or handle async
}

$checkout = $checkoutService->subscriptionCheckout(
    owner: $user,
    price: $price,
    successUrl: route('subscriptions.success'),
    cancelUrl: route('subscriptions.cancel'),
    metadata: ['source' => 'admin_panel']
);

// Redirect to Stripe Checkout
return redirect($checkout->asStripeCheckoutSession()->url);
```

#### One-Time Payment Checkout

```php
$checkout = $checkoutService->oneTimeCheckout(
    owner: $user,
    price: $oneTimePrice,
    quantity: 1,
    successUrl: route('payments.success'),
    cancelUrl: route('payments.cancel'),
);

return redirect($checkout->asStripeCheckoutSession()->url);
```

### Using Factories in Tests

The package includes factories that are automatically available:

```php
use LaravelCatalog\Models\Product;
use LaravelCatalog\Models\Price;

// Create a product
$product = Product::factory()->create();

// Create a product with prices
$product = Product::factory()->create();
$price = Price::factory()->for($product)->create();

// Create a recurring price
$recurringPrice = Price::factory()
    ->for($product)
    ->create([
        'type' => Price::TYPE_RECURRING,
        'recurring_interval' => 'month',
    ]);

// Create a one-time price
$oneTimePrice = Price::factory()
    ->for($product)
    ->oneTime()
    ->create();
```

## Admin Interface

### Accessing the Admin UI

After registering routes, access the admin interface at:

```
/ctrl/products
```

The interface includes:
- **Plans Tab**: Shows products marked for storefront display
- **Products Tab**: Shows all products
- **Features Tab**: Manage product features
- **Settings Tab**: Catalog configuration

### Features

- Create and edit products
- Manage prices (recurring and one-time)
- Sync products to Stripe
- Bulk sync operations
- Product feature management

## Testing

The package includes comprehensive tests. Run them with:

```bash
php artisan test tests/Feature/Catalog/
```

### Test Setup

The package migrations are automatically loaded in tests. Ensure your test database is set up:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
```

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
│   ├── views/
│   └── css/
└── config/
    └── catalog.php
```

## Quick Start Guide

1. **Install the package**:
   ```bash
   composer require particle-academy/laravel-catalog
   ```

2. **Run migrations**:
   ```bash
   php artisan migrate
   ```

3. **Publish config** (optional):
   ```bash
   php artisan vendor:publish --tag=catalog-config
   ```

4. **Register routes** in `routes/web.php`:
   ```php
   Route::prefix('ctrl')->name('admin.')->middleware(['web', 'auth'])->group(function () {
       Route::get('/products', \LaravelCatalog\Livewire\Admin\Products\Index::class)->name('products.index');
   });
   ```

5. **Access admin UI** at `/ctrl/products`

6. **Create products** via admin UI or programmatically:
   ```php
   $product = Product::factory()->create(['name' => 'My Product']);
   ```

7. **Sync to Stripe**:
   ```php
   SyncProductToStripe::dispatch($product->id);
   ```

## Integration with Test App

When integrating into a test application:

1. **Add to composer.json** (for local development):
   ```json
   {
       "repositories": [
           {
               "type": "path",
               "url": "./packages/laravel-catalog",
               "options": {
                   "symlink": true
               }
           }
       ],
       "require": {
           "particle-academy/laravel-catalog": "@dev"
       }
   }
   ```

2. **Run composer update**:
   ```bash
   composer update particle-academy/laravel-catalog
   ```

3. **Migrations load automatically** - no need to publish them

4. **Factories are available** - use `Product::factory()` and `Price::factory()` directly

5. **Configure middleware** in `config/catalog.php` for your test app's auth setup

## Broadcasting

The package broadcasts `ProductSyncedToStripe` events. Configure broadcasting in `routes/channels.php`:

```php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(config('catalog.broadcast_channel', 'admin.products'), function ($user) {
    return $user->isAdmin(); // Adjust based on your auth logic
});
```

## License

MIT
