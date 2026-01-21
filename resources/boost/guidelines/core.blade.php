## Laravel Catalog Package

This package provides Stripe catalog (Products and Prices) management with an optional admin UI. All functionality is accessible via a facade, making it perfect for apps using their own UX. Plans are Products with recurring Prices - there is no separate Plan model.

### Features

- **Product Management**: Create, edit, and manage Stripe products with full CRUD operations
- **Price Management**: Manage recurring (subscription) and one-time prices for products
- **Plans Support**: Plans are simply Products with recurring Prices - no separate model needed
- **Stripe Sync**: Automatic or manual synchronization with Stripe's catalog
- **Facade API**: Complete programmatic access via `Catalog` facade - no UI required
- **Optional Admin UI**: Complete Livewire-based admin interface (optional, requires publishing)
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
- `src/Livewire/Admin/Products/Index.php` - Admin UI component (optional)

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
    'auto_sync' => env('CATALOG_AUTO_SYNC', false),
    'queue_connection' => env('CATALOG_QUEUE_CONNECTION', 'default'),
    'enable_ui' => env('CATALOG_ENABLE_UI', false),
    'admin_route_prefix' => env('CATALOG_ADMIN_PREFIX', 'admin/catalog'),
    'admin_middleware' => ['web', 'auth'],
    'broadcasting_channel' => env('CATALOG_BROADCASTING_CHANNEL', 'catalog-sync'),
];
</code-snippet>
@endverbatim

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
- Enable UI only when needed - the package works without UI
- Use Product Features with FMS for feature-based access control
- Always handle Stripe API errors gracefully
