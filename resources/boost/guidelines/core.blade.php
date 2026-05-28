## Laravel Catalog Package

This package provides Stripe catalog (Products and Prices) management via a facade API. Built for apps that bring their own UI. Plans are Products with recurring Prices - there is no separate Plan model.

### Features

- **Product Management**: Create, edit, and manage Stripe products with full CRUD operations
- **Price Management**: Manage recurring (subscription) and one-time prices for products
- **Plans Support**: Plans are simply Products with recurring Prices - no separate model needed
- **Stripe Sync**: Automatic or manual synchronization with Stripe's catalog
- **Facade API**: Complete programmatic access via `Catalog` facade - bring your own UI
- **Product Features**: Support for product features and feature configurations via FMS integration
- **Checkout Integration**: Ready-to-use Stripe Checkout session creation for subscriptions and one-time payments

### File Structure

- `src/CatalogManager.php` - Main facade implementation providing unified interface
- `src/Services/StripeCatalogService.php` - Handles Stripe product/price synchronization
- `src/Services/StripeCheckoutService.php` - Handles Stripe Checkout session creation
- `src/Models/Product.php` - Product model with Stripe sync fields
- `src/Models/Price.php` - Price model for recurring and one-time pricing
- `src/Models/ProductFeature.php` - Product features model for FMS integration
- `src/Facades/Catalog.php` - Facade for accessing catalog functionality

### Core Concepts

**Important**: Every Product must have at least one Price before it can be synced to Stripe. Plans are Products with recurring Prices - there is no separate Plan model.

### Configuration

Publish the configuration file:

@verbatim
<code-snippet name="Publish Catalog config" lang="bash">
php artisan vendor:publish --tag=catalog-config
</code-snippet>
@endverbatim

Configure in `config/catalog.php`:

@verbatim
<code-snippet name="Catalog Configuration" lang="php">
return [
    'auto_sync_stripe' => env('CATALOG_AUTO_SYNC_STRIPE', false),
    'queue_connection' => env('CATALOG_QUEUE_CONNECTION', 'default'),
    'broadcast_channel' => 'admin.products',

    // Override table names when your schema differs (e.g. prefixed).
    // Models AND migrations read these; create migrations self-skip when
    // the table exists or an FK target is absent.
    'tables' => [
        'products' => 'products',
        'prices' => 'prices',
        'product_features' => 'product_features',
        'product_feature_configs' => 'product_feature_configs',
    ],
];
</code-snippet>
@endverbatim

When your app already has a `products` table, prefix catalog's
(`catalog_products`, etc.) via the `tables` block — no fork needed. This
mirrors `laravel-fms` v0.7.0's `fms.tables`.

### Using the Catalog Facade

@verbatim
<code-snippet name="Catalog Facade Usage" lang="php">
use LaravelCatalog\Facades\Catalog;

// Create a product
$product = Catalog::createProduct([
    'name' => 'Pro Plan',
    'description' => 'Professional features',
    'metadata' => ['key' => 'value'],
]);

// Create a price
$price = Catalog::createPrice($product, [
    'amount' => 2999, // in cents
    'currency' => 'usd',
    'recurring' => [
        'interval' => 'month',
    ],
]);

// Sync product to Stripe
Catalog::syncProductAndPrices($product);

// Create checkout session
$checkout = Catalog::createCheckoutSession($user, [
    'price' => $price->stripe_price_id,
    'success_url' => route('checkout.success'),
    'cancel_url' => route('checkout.cancel'),
]);
</code-snippet>
@endverbatim

### Creating Products

@verbatim
<code-snippet name="Create Product" lang="php">
use LaravelCatalog\Models\Product;
use LaravelCatalog\Facades\Catalog;

// Using facade
$product = Catalog::createProduct([
    'name' => 'Basic Plan',
    'description' => 'Basic features',
    'active' => true,
    'metadata' => ['plan_type' => 'basic'],
]);

// Using model directly
$product = Product::create([
    'name' => 'Pro Plan',
    'description' => 'Professional features',
    'active' => true,
]);
</code-snippet>
@endverbatim

### Creating Prices

@verbatim
<code-snippet name="Create Price" lang="php">
use LaravelCatalog\Models\Price;
use LaravelCatalog\Facades\Catalog;

// Recurring price (subscription)
$recurringPrice = Catalog::createPrice($product, [
    'amount' => 2999, // $29.99 in cents
    'currency' => 'usd',
    'recurring' => [
        'interval' => 'month',
        'interval_count' => 1,
    ],
]);

// One-time price
$oneTimePrice = Catalog::createPrice($product, [
    'amount' => 9999, // $99.99 in cents
    'currency' => 'usd',
    'type' => 'one_time',
]);
</code-snippet>
@endverbatim

### Syncing to Stripe

@verbatim
<code-snippet name="Sync to Stripe" lang="php">
use LaravelCatalog\Facades\Catalog;

// Sync single product and its prices
Catalog::syncProductAndPrices($product);

// Sync all products (if auto_sync is disabled)
Catalog::syncAllProducts();

// Queue sync job
Catalog::queueSyncProduct($product);
</code-snippet>
@endverbatim

### Creating Checkout Sessions

@verbatim
<code-snippet name="Create Checkout Session" lang="php">
use LaravelCatalog\Facades\Catalog;

// Subscription checkout
$checkout = Catalog::createCheckoutSession($user, [
    'price' => $price->stripe_price_id,
    'success_url' => route('checkout.success'),
    'cancel_url' => route('checkout.cancel'),
    'mode' => 'subscription',
]);

// One-time payment checkout
$checkout = Catalog::createCheckoutSession($user, [
    'price' => $oneTimePrice->stripe_price_id,
    'success_url' => route('checkout.success'),
    'cancel_url' => route('checkout.cancel'),
    'mode' => 'payment',
]);

// Redirect to checkout
return redirect($checkout->url);
</code-snippet>
@endverbatim

### Working with Product Features

@verbatim
<code-snippet name="Product Features" lang="php">
use LaravelCatalog\Models\Product;
use LaravelCatalog\Models\ProductFeature;

// Attach feature to product
$product = Product::find($productId);
$feature = ProductFeature::where('key', 'advanced-editing')->first();

$product->productFeatures()->attach($feature->id, [
    'enabled' => true,
    'included_quantity' => 100,
]);

// Check if product has feature
if ($product->productFeatures()->where('key', 'advanced-editing')->exists()) {
    // Product has feature
}
</code-snippet>
@endverbatim

### Integration with FMS

When FMS is installed, Catalog automatically configures FMS to use Catalog's `ProductFeature` model:

@verbatim
<code-snippet name="FMS Integration" lang="php">
use ParticleAcademy\Fms\Facades\FMS;
use LaravelCatalog\Models\Product;

// Check feature access for user's subscription
$user = auth()->user();
$subscription = $user->subscriptions()->active()->first();

if ($subscription) {
    $product = $subscription->product();
    
    foreach ($product->productFeatures as $feature) {
        if (FMS::canAccess($feature->key, $user)) {
            // Feature is available
        }
    }
}
</code-snippet>
@endverbatim

### Best Practices

- Always create at least one Price before syncing a Product to Stripe
- Use the Catalog facade for all operations to maintain consistency
- Queue sync operations for better performance in production
- Use metadata fields for custom product configurations
- Use Product Features with FMS for feature-based access control
- Always handle Stripe API errors gracefully
