[![Powered by Tynn](https://img.shields.io/endpoint?url=https%3A%2F%2Ftynn.ai%2Fo%2Fparticle-academy%2Flaravel-catalog%2Fbadge.json)](https://tynn.ai/o/particle-academy/laravel-catalog)
# Laravel Catalog Package

A Laravel package for managing Stripe catalog (Products and Prices) with an optional admin UI. All functionality is accessible via a facade, making it perfect for apps using their own UX.

> **Important**: Every Product must have at least one Price before it can be synced to Stripe. Plans are Products with recurring Prices - there is no separate Plan model.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Core Concepts](#core-concepts)
  - [Products, Plans, and Prices](#products-plans-and-prices)
  - [Price Requirements](#price-requirements)
- [Usage](#usage)
  - [Using the Catalog Facade](#using-the-catalog-facade)
  - [Creating Products](#creating-products)
  - [Creating Prices](#creating-prices)
  - [Working with Plans](#working-with-plans)
  - [Syncing to Stripe](#syncing-to-stripe)
  - [Creating Checkout Sessions](#creating-checkout-sessions)
- [Creating Your Own Admin UI](#creating-your-own-admin-ui)
- [Admin Interface (Published UI)](#admin-interface-published-ui)
- [Integration with FMS](#integration-with-fms)
- [Testing](#testing)
- [Common Patterns](#common-patterns)

## Features

- **Product Management**: Create, edit, and manage Stripe products with full CRUD operations
- **Price Management**: Manage recurring (subscription) and one-time prices for products
- **Plans Support**: Plans are simply Products with recurring Prices - no separate model needed
- **Stripe Sync**: Automatic or manual synchronization with Stripe's catalog
- **Facade API**: Complete programmatic access via `Catalog` facade - no UI required
- **Optional Admin UI**: Complete Livewire-based admin interface (optional, requires publishing)
- **Product Features**: Support for product features and feature configurations via FMS integration
- **Checkout Integration**: Ready-to-use Stripe Checkout session creation for subscriptions and one-time payments
- **Queue Support**: Background sync jobs for better performance
- **Event Broadcasting**: Real-time sync status updates via Laravel Broadcasting
- **Soft Deletes**: Products and Prices use soft deletes to preserve financial history
- **Metadata Support**: Flexible metadata storage for custom product configurations
- **Storefront Configuration**: Built-in support for storefront plan visibility and recommendations

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

### Step 3: Run Migrations

The package automatically loads both its own migrations and Laravel Cashier migrations (since Catalog depends on Cashier).

```bash
php artisan migrate
```

The package includes these migrations:
- **Cashier migrations** (auto-loaded):
  - `create_customer_columns` - Stripe customer columns on users table
  - `create_subscriptions_table` - Subscriptions table
  - `create_subscription_items_table` - Subscription items table
- **Catalog migrations** (auto-loaded):
  - `create_products_table` - Products table with Stripe sync fields
  - `create_prices_table` - Prices table for recurring and one-time pricing
  - `create_product_features_table` - Product features table
  - `create_product_feature_configs_table` - Product-feature pivot table

### Step 4: Enable UI (Optional)

The package works without UI by default. To enable the admin UI:

1. **Set UI enabled in config** (or publish config and set `CATALOG_ENABLE_UI=true` in `.env`):

```php
// config/catalog.php
'enable_ui' => env('CATALOG_ENABLE_UI', false),
```

2. **Publish views and assets**:

```bash
php artisan vendor:publish --tag=catalog-views
php artisan vendor:publish --tag=catalog-assets
```

3. **Register admin routes** in `routes/web.php`:

```php
use LaravelCatalog\Livewire\Admin\Products\Index as ProductsIndex;

Route::prefix('ctrl')->name('admin.')->middleware(config('catalog.admin_middleware'))->group(function () {
    Route::get('/products', ProductsIndex::class)->name('products.index');
});
```

**Note**: The UI uses standard Tailwind CSS classes and does not require the custom CSS file. The UI will automatically be enabled if views are published, even without setting `enable_ui` to true.

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
CATALOG_ENABLE_UI=false  # Set to true to enable admin UI
```

### Configuration File

After publishing, edit `config/catalog.php`:

```php
return [
    // Enable UI components (Livewire, views, routes)
    // UI will also be enabled automatically if views are published
    'enable_ui' => env('CATALOG_ENABLE_UI', false),

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

## Core Concepts

### Products, Plans, and Prices

- **Products**: Containers that hold pricing information. Can represent subscription plans, one-time purchases, or add-ons.
- **Plans**: Products with recurring Prices. There is no separate "Plan" model. A plan is a Product that:
  - Has at least one recurring Price (`type = 'recurring'`)
  - Is optionally marked for storefront display (`metadata->storefront->plan->show = true`)
- **Prices**: Define the actual pricing (amount, currency, interval). Every Product **must** have at least one Price.

### Price Requirements

**Critical**: Products cannot be synced to Stripe without at least one Price. Always create a Price when creating a Product:

```php
// ❌ WRONG - Product without Price
$product = Product::create(['name' => 'My Product']);
Catalog::syncProduct($product); // Will fail or create incomplete Stripe product

// ✅ CORRECT - Product with Price
$product = Product::create(['name' => 'My Product']);
Price::create([
    'product_id' => $product->id,
    'unit_amount' => 2900,
    'currency' => 'USD',
    'type' => Price::TYPE_RECURRING,
    'recurring_interval' => 'month',
]);
Catalog::syncProductAndPrices($product); // Success!
```

## Usage

### Using the Catalog Facade

All catalog functionality is accessible via the `Catalog` facade, making it easy to use without the UI:

```php
use LaravelCatalog\Facades\Catalog;
use LaravelCatalog\Models\Product;
use LaravelCatalog\Models\Price;

// Sync a product to Stripe (requires at least one Price)
$product = Product::with('prices')->find('product-id');
if ($product->prices->isEmpty()) {
    throw new \Exception('Product must have at least one Price before syncing.');
}
Catalog::syncProduct($product);

// Sync a price to Stripe
$price = Price::find('price-id');
Catalog::syncPrice($price);

// Sync product and all its prices (recommended)
Catalog::syncProductAndPrices($product);

// Test Stripe connection
$result = Catalog::testConnection();
// Returns: ['success' => true, 'message' => 'Connection successful']

// Create checkout session for subscription
$checkout = Catalog::subscriptionCheckout(
    owner: $user,
    price: $price, // Must be a recurring price
    successUrl: route('subscriptions.success'),
    cancelUrl: route('subscriptions.cancel'),
    metadata: ['source' => 'admin_panel'] // Optional
);

// Create checkout session for one-time payment
$checkout = Catalog::oneTimeCheckout(
    owner: $user,
    price: $price, // Must be a one-time price
    quantity: 1,
    successUrl: route('payments.success'),
    cancelUrl: route('payments.cancel'),
    metadata: [] // Optional
);

// Get checkout URLs directly (convenience methods)
$url = Catalog::getSubscriptionCheckoutUrl($user, $price, $successUrl, $cancelUrl);
$url = Catalog::getOneTimeCheckoutUrl($user, $price, 1, $successUrl, $cancelUrl);

// Access services directly if needed
Catalog::catalogService()->testConnection();
Catalog::checkoutService()->oneTimeCheckout(...);
```

### Important Notes

- **Products Must Have Prices**: A Product cannot be synced to Stripe without at least one Price. Always create a Price when creating a Product.
- **Plans are Products**: There is no separate "Plan" model. Plans are Products with recurring Prices and storefront metadata.
- **Price Types**: Prices can be `recurring` (subscriptions) or `one_time` (one-time purchases).
- **Sync Before Checkout**: Always ensure Products/Prices are synced to Stripe before creating checkout sessions.

> For detailed explanations of Products, Plans, and Prices, see [Core Concepts](#core-concepts) above.

### Creating Products

```php
use LaravelCatalog\Models\Product;
use LaravelCatalog\Models\Price;

// Create a product (plan)
$product = Product::create([
    'name' => 'Pro Plan',
    'description' => 'Perfect for growing teams',
    'active' => true,
    'order' => 1,
    'metadata' => [
        'storefront' => [
            'plan' => [
                'show' => true,        // Show on storefront
                'recommended' => true,  // Mark as recommended
            ],
        ],
    ],
]);

// IMPORTANT: Create at least one Price for the Product
// Recurring monthly price (makes this a "plan")
$monthlyPrice = Price::create([
    'product_id' => $product->id,
    'unit_amount' => 2900, // $29.00 in cents
    'currency' => 'USD',
    'recurring_interval' => 'month',
    'recurring_interval_count' => 1,
    'type' => Price::TYPE_RECURRING,
    'active' => true,
]);

// You can add multiple prices to the same product
// Yearly price (same product, different billing interval)
$yearlyPrice = Price::create([
    'product_id' => $product->id,
    'unit_amount' => 29000, // $290.00 in cents (save $58/year)
    'currency' => 'USD',
    'recurring_interval' => 'year',
    'recurring_interval_count' => 1,
    'type' => Price::TYPE_RECURRING,
    'active' => true,
]);
```

### Creating Prices

```php
use LaravelCatalog\Models\Price;

// Recurring monthly price (for subscription plans)
$monthlyPrice = Price::create([
    'product_id' => $product->id,
    'unit_amount' => 2900, // $29.00 in cents
    'currency' => 'USD',
    'recurring_interval' => 'month',
    'recurring_interval_count' => 1,
    'recurring_trial_period_days' => 14, // Optional trial period
    'type' => Price::TYPE_RECURRING,
    'active' => true,
]);

// Recurring yearly price
$yearlyPrice = Price::create([
    'product_id' => $product->id,
    'unit_amount' => 29000, // $290.00 in cents
    'currency' => 'USD',
    'recurring_interval' => 'year',
    'recurring_interval_count' => 1,
    'type' => Price::TYPE_RECURRING,
    'active' => true,
]);

// One-time price (for add-ons or one-time purchases)
$oneTimePrice = Price::create([
    'product_id' => $product->id,
    'unit_amount' => 9900, // $99.00 in cents
    'currency' => 'USD',
    'type' => Price::TYPE_ONE_TIME,
    'active' => true,
]);

// Using factory (recommended for tests)
$recurringPrice = Price::factory()
    ->for($product)
    ->create([
        'type' => Price::TYPE_RECURRING,
        'recurring_interval' => 'month',
    ]);

$oneTimePrice = Price::factory()
    ->for($product)
    ->oneTime()
    ->create([
        'unit_amount' => 9900,
    ]);
```

### Working with Plans

Since plans are Products with recurring Prices, you can query them like this:

```php
use LaravelCatalog\Models\Product;
use LaravelCatalog\Models\Price;

// Get all products that are plans (have recurring prices and are marked for storefront)
$plans = Product::whereHas('prices', function ($query) {
    $query->where('type', Price::TYPE_RECURRING);
})
->whereJsonContains('metadata->storefront->plan->show', true)
->with(['prices' => function ($query) {
    $query->where('type', Price::TYPE_RECURRING);
}])
->orderBy('order')
->get();

// Get the recommended plan
$recommendedPlan = Product::whereJsonContains('metadata->storefront->plan->recommended', true)
    ->with('prices')
    ->first();

// Check if a product is a plan
if ($product->isStorefrontPlan()) {
    // This is a plan shown on the storefront
}

// Get all recurring prices for a product (its plan options)
$planPrices = $product->prices()->where('type', Price::TYPE_RECURRING)->get();
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
use LaravelCatalog\Facades\Catalog;
use LaravelCatalog\Models\Price;
use App\Models\User;

$user = User::find(1);
$price = Price::find($priceId);

// Ensure price has been synced to Stripe first
if (!$price->stripePriceId()) {
    Catalog::syncProductAndPrices($price->product);
}

$checkout = Catalog::subscriptionCheckout(
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
use LaravelCatalog\Facades\Catalog;

$checkout = Catalog::oneTimeCheckout(
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

## Creating Your Own Admin UI

The package is designed to work without any UI dependencies. You can build your own admin interface using only the `Catalog` facade and Eloquent models.

### Key Principles

1. **Use the Catalog Facade**: All Stripe operations go through `LaravelCatalog\Facades\Catalog`
2. **Products Must Have Prices**: Always create at least one Price when creating a Product
3. **Plans are Products**: Filter Products with recurring Prices to get plans
4. **Sync Before Checkout**: Ensure Products/Prices are synced to Stripe before creating checkout sessions

### Example: Custom Admin Controller

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LaravelCatalog\Facades\Catalog;
use LaravelCatalog\Models\Product;
use LaravelCatalog\Models\Price;

class ProductsController extends Controller
{
    public function index()
    {
        $products = Product::with('prices')
            ->orderBy('order')
            ->paginate(20);
        
        return view('admin.products.index', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_amount' => 'required|integer|min:1', // Price amount in cents
            'currency' => 'required|string|size:3',
            'recurring_interval' => 'required_if:type,recurring|in:month,year,week,day',
            'type' => 'required|in:recurring,one_time',
        ]);

        // Create product
        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => true,
        ]);

        // IMPORTANT: Create at least one price (required for Stripe sync)
        $price = Price::create([
            'product_id' => $product->id,
            'unit_amount' => $validated['unit_amount'],
            'currency' => $validated['currency'],
            'type' => $validated['type'],
            'recurring_interval' => $validated['type'] === 'recurring' 
                ? $validated['recurring_interval'] 
                : null,
            'recurring_interval_count' => $validated['type'] === 'recurring' ? 1 : null,
            'active' => true,
        ]);

        // Sync to Stripe
        Catalog::syncProductAndPrices($product);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created and synced to Stripe.');
    }

    public function sync(Product $product)
    {
        // Ensure product has at least one price
        if ($product->prices->isEmpty()) {
            return redirect()->back()
                ->with('error', 'Product must have at least one Price before syncing.');
        }

        try {
            Catalog::syncProductAndPrices($product);
            return redirect()->back()
                ->with('success', 'Product synced to Stripe successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }
}
```

### Example: Plans Management

**Remember**: Plans are Products with recurring Prices. There is no separate Plan model.

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LaravelCatalog\Facades\Catalog;
use LaravelCatalog\Models\Product;
use LaravelCatalog\Models\Price;

class PlansController extends Controller
{
    public function index()
    {
        // Get all products that are plans (have recurring prices and marked for storefront)
        $plans = Product::whereHas('prices', function ($query) {
            $query->where('type', Price::TYPE_RECURRING);
        })
        ->whereJsonContains('metadata->storefront->plan->show', true)
        ->with(['prices' => function ($query) {
            $query->where('type', Price::TYPE_RECURRING);
        }])
        ->orderBy('order')
        ->get();
        
        return view('admin.plans.index', compact('plans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monthly_amount' => 'required|integer|min:1', // At least monthly price required
            'yearly_amount' => 'nullable|integer|min:1',
            'show_on_storefront' => 'boolean',
            'recommended' => 'boolean',
        ]);

        // Create product with plan metadata
        $metadata = [];
        if ($validated['show_on_storefront'] ?? false) {
            $metadata['storefront']['plan']['show'] = true;
            if ($validated['recommended'] ?? false) {
                $metadata['storefront']['plan']['recommended'] = true;
            }
        }

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'metadata' => $metadata,
            'active' => true,
        ]);

        // IMPORTANT: Create at least one recurring price (required)
        Price::create([
            'product_id' => $product->id,
            'unit_amount' => $validated['monthly_amount'],
            'currency' => 'USD',
            'type' => Price::TYPE_RECURRING,
            'recurring_interval' => 'month',
            'recurring_interval_count' => 1,
            'active' => true,
        ]);

        // Create yearly price if provided (optional, same product)
        if (isset($validated['yearly_amount'])) {
            Price::create([
                'product_id' => $product->id,
                'unit_amount' => $validated['yearly_amount'],
                'currency' => 'USD',
                'type' => Price::TYPE_RECURRING,
                'recurring_interval' => 'year',
                'recurring_interval_count' => 1,
                'active' => true,
            ]);
        }

        // Sync to Stripe
        Catalog::syncProductAndPrices($product);

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan created and synced to Stripe.');
    }
}
```

### Example: Using FMS for Feature Management

```php
<?php

use ParticleAcademy\Fms\Facades\FMS;
use LaravelCatalog\Models\Product;

// Check if user can access a feature based on their subscription
if (FMS::canAccess('advanced-editing', $user)) {
    // User has access to advanced editing feature
}

// Get remaining quantity for resource features
$remaining = FMS::remaining('api-calls', $user);

// Check feature access in controller
public function edit(Product $product)
{
    if (!FMS::canAccess('edit-products', auth()->user())) {
        abort(403, 'You do not have permission to edit products.');
    }
    
    return view('admin.products.edit', compact('product'));
}
```

See the [FMS Integration](#integration-with-fms) section below for more details.

## Admin Interface (Published UI)

### Accessing the Admin UI

After enabling UI and registering routes, access the admin interface at:

```
/ctrl/products
```

The interface includes:
- **Plans Tab**: Shows products marked for storefront display (products with recurring prices)
- **Products Tab**: Shows all products (both recurring and one-time)
- **Features Tab**: Manage product features (requires FMS integration)
- **Settings Tab**: Catalog configuration and storefront settings

### Features

- Create and edit products
- Manage prices (recurring and one-time)
- Sync products to Stripe
- Bulk sync operations
- Product feature management (with FMS)
- Storefront configuration (plan visibility, recommendations)

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

4. **Create your first product with price**:
   ```php
   use LaravelCatalog\Models\Product;
   use LaravelCatalog\Models\Price;
   use LaravelCatalog\Facades\Catalog;
   
   // Create product
   $product = Product::create([
       'name' => 'Pro Plan',
       'description' => 'Perfect for growing teams',
       'active' => true,
   ]);
   
   // IMPORTANT: Create at least one Price (required for Stripe sync)
   $price = Price::create([
       'product_id' => $product->id,
       'unit_amount' => 2900, // $29.00 in cents
       'currency' => 'USD',
       'type' => Price::TYPE_RECURRING,
       'recurring_interval' => 'month',
       'recurring_interval_count' => 1,
       'active' => true,
   ]);
   
   // Sync to Stripe
   Catalog::syncProductAndPrices($product);
   ```

5. **Optional: Enable Admin UI**:
   
   If you want to use the published admin UI:
   ```bash
   php artisan vendor:publish --tag=catalog-views
   php artisan vendor:publish --tag=catalog-assets
   ```
   
   Then register routes in `routes/web.php`:
   ```php
   Route::prefix('ctrl')->name('ctrl.')->middleware(['web', 'auth'])->group(function () {
       Route::get('/products', \LaravelCatalog\Livewire\Admin\Products\Index::class)->name('products.index');
   });
   ```
   
   Access admin UI at `/ctrl/products`

6. **Or build your own UI**:
   
   Use the `Catalog` facade to build a custom admin interface. See [Creating Your Own Admin UI](#creating-your-own-admin-ui) section below.

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

## Integration with FMS

Laravel Catalog integrates seamlessly with the [Laravel Feature Management System (FMS)](https://github.com/particle-academy/laravel-fms) package for feature-based access control.

### Installing FMS

```bash
composer require particle-academy/laravel-fms
```

### How Integration Works

1. **Automatic Configuration**: When FMS is installed, Catalog automatically configures FMS to use Catalog's `ProductFeature` model.

2. **Product Features**: Products can have features attached via the `productFeatures()` relationship. These features are managed through the `ProductFeature` model.

3. **Feature Access Control**: Use FMS to check feature access based on user subscriptions:

```php
use ParticleAcademy\Fms\Facades\FMS;
use LaravelCatalog\Models\Product;

// Check if user has access to a feature
if (FMS::canAccess('advanced-editing', $user)) {
    // User has access
}

// Get remaining quantity for resource features
$remaining = FMS::remaining('api-calls', $user);
```

### Example: Feature-Based Product Access

```php
// In your controller
use ParticleAcademy\Fms\Facades\FMS;

public function show(Product $product)
{
    // Check if user has access to this product's features
    $features = $product->productFeatures;
    
    foreach ($features as $feature) {
        if (!FMS::canAccess($feature->key, auth()->user())) {
            abort(403, "You don't have access to {$feature->name}");
        }
    }
    
    return view('products.show', compact('product'));
}
```

### Configuring Features for Catalog

Define features in `config/fms.php`:

```php
return [
    'features' => [
        'manage-products' => [
            'name' => 'Manage Products',
            'description' => 'Create, edit, and delete products',
            'type' => 'boolean',
            'enabled' => fn($user) => $user->hasRole('admin'),
        ],
        
        'product-creations' => [
            'name' => 'Product Creations',
            'description' => 'Monthly product creation limit',
            'type' => 'resource',
            'limit' => 100,
            'usage' => fn($user) => Product::where('created_by', $user->id)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ],
    ],
];
```

### Using FMS Middleware

Protect catalog routes with FMS:

```php
use ParticleAcademy\Fms\Http\Middleware\RequireFeature;

Route::prefix('admin')->middleware([
    'auth',
    RequireFeature::class . ':manage-products'
])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
});
```

For more detailed FMS integration examples, see the [FMS Integration Guide](https://github.com/particle-academy/laravel-fms/blob/main/INTEGRATION.md).

## Broadcasting

The package broadcasts `ProductSyncedToStripe` events. Configure broadcasting in `routes/channels.php`:

```php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(config('catalog.broadcast_channel', 'admin.products'), function ($user) {
    return $user->isAdmin(); // Adjust based on your auth logic
});
```

## Common Patterns

### Pattern 1: Creating a Plan with Multiple Prices

```php
$product = Product::create([
    'name' => 'Pro Plan',
    'metadata' => ['storefront' => ['plan' => ['show' => true]]],
]);

// Monthly price
Price::create([
    'product_id' => $product->id,
    'unit_amount' => 2900,
    'currency' => 'USD',
    'type' => Price::TYPE_RECURRING,
    'recurring_interval' => 'month',
]);

// Yearly price (same product)
Price::create([
    'product_id' => $product->id,
    'unit_amount' => 29000,
    'currency' => 'USD',
    'type' => Price::TYPE_RECURRING,
    'recurring_interval' => 'year',
]);
```

### Pattern 2: Syncing Before Checkout

```php
use LaravelCatalog\Facades\Catalog;

$price = Price::find($priceId);

// Ensure price is synced before creating checkout
if (!$price->external_id) {
    Catalog::syncProductAndPrices($price->product);
    $price->refresh();
}

// Now create checkout
$checkout = Catalog::subscriptionCheckout(
    $user,
    $price,
    route('success'),
    route('cancel')
);
```

### Pattern 3: Querying Plans vs Products

```php
// Get all plans (products with recurring prices marked for storefront)
$plans = Product::whereHas('prices', function ($q) {
    $q->where('type', Price::TYPE_RECURRING);
})
->whereJsonContains('metadata->storefront->plan->show', true)
->get();

// Get all products (including one-time purchases)
$allProducts = Product::with('prices')->get();

// Get one-time products (add-ons)
$addons = Product::whereHas('prices', function ($q) {
    $q->where('type', Price::TYPE_ONE_TIME);
})
->whereJsonContains('metadata->storefront->addon->show', true)
->get();
```

## License

MIT
