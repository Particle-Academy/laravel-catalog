<?php

namespace LaravelCatalog\Livewire\Admin\Products;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use LaravelCatalog\Models\Product;
use LaravelCatalog\Models\ProductFeature;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin Products Index Component
 * Created to provide the admin interface for managing Stripe catalog products and prices.
 * This component handles product listing, creation, editing, syncing, and deletion.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $activeTab = 'plans';

    public bool $showCreateProduct = false;

    public bool $showDeleteConfirm = false;

    public ?string $editingProductId = null;

    public string $productTab = 'details';

    public string $productName = '';

    public ?string $productDescription = null;

    public bool $productActive = true;

    public string $productNameToDelete = '';

    public array $catalogFeatureSettings = [];

    public array $productImages = [];

    public array $productMetadataKeys = [];

    public array $productMetadataValues = [];

    public array $storefrontPlans = [];

    public array $storefrontAddons = [];

    public ?string $recommendedPlanProductId = null;

    public function getProductPricesProperty(): array
    {
        if (! $this->editingProductId) {
            return [];
        }

        $product = Product::with('prices')->find($this->editingProductId);

        if (! $product) {
            return [];
        }

        return $product->prices->map(function ($price) {
            return [
                'id' => $price->id,
                'unit_amount_dollars' => $price->unit_amount / 100,
                'currency' => $price->currency,
                'type' => $price->type,
                'recurring_interval' => $price->recurring_interval,
                'recurring_interval_count' => $price->recurring_interval_count,
                'recurring_trial_period_days' => $price->recurring_trial_period_days,
                'lookup_key' => $price->lookup_key,
                'nickname' => $price->nickname,
                'billing_scheme' => $price->billing_scheme,
                'active' => $price->active,
            ];
        })->toArray();
    }

    public function mount(): void
    {
        $tab = request()->query('tab', 'plans');
        if (in_array($tab, ['plans', 'products', 'features', 'settings'])) {
            $this->activeTab = $tab;
        }

        $this->loadStorefrontSettings();
        $this->loadCatalogFeatures();
    }

    public function render(): View
    {
        $products = $this->getProducts();

        // Get all products for settings tab
        $allProducts = Product::with(['prices'])->orderBy('order')->get();

        return view('catalog::livewire.admin.products.index', [
            'products' => $products,
            'allProducts' => $allProducts,
            'productPrices' => $this->productPrices,
        ]);
    }

    protected function getProducts(): LengthAwarePaginator
    {
        $query = Product::with(['prices', 'productFeatures'])
            ->orderBy('order')
            ->orderBy('created_at', 'desc');

        if ($this->activeTab === 'plans') {
            // Show only products marked for storefront plans
            $query->whereJsonContains('metadata->storefront->plan->show', true);
        } elseif ($this->activeTab === 'products') {
            // Show all products (both recurring and one-time)
            // No filter needed
        }
        // Features and Settings tabs don't need product filtering

        return $query->paginate(15);
    }

    public function openCreateProduct(): void
    {
        $this->resetProductForm();
        $this->showCreateProduct = true;
        $this->editingProductId = null;
    }

    public function openEditProduct(string $productId): void
    {
        $product = Product::findOrFail($productId);
        $this->editingProductId = $productId;
        $this->productName = $product->name;
        $this->productDescription = $product->description;
        $this->productActive = $product->active;
        $this->productTab = 'details';
        $this->productImages = $product->images ?? [];
        $this->productMetadataKeys = $product->metadata ? array_keys($product->metadata) : [];
        $this->productMetadataValues = $product->metadata ? array_values($product->metadata) : [];
        $this->showCreateProduct = true;
    }

    public function saveProduct(): void
    {
        // TODO: Implement product save logic
        $this->showCreateProduct = false;
        $this->resetProductForm();
    }

    public function deleteProduct(): void
    {
        // TODO: Implement product deletion logic
        $this->showDeleteConfirm = false;
        $this->resetProductForm();
    }

    public function cancelDeleteProduct(): void
    {
        $this->showDeleteConfirm = false;
        $this->productNameToDelete = '';
    }

    public function syncProductNow(string $productId): void
    {
        // TODO: Implement sync logic
    }

    public function syncAllProducts(): void
    {
        // TODO: Implement bulk sync logic
    }

    public function saveCatalogFeatures(): void
    {
        foreach ($this->catalogFeatureSettings as $featureId => $settings) {
            $feature = ProductFeature::find($featureId);
            if ($feature) {
                $feature->update([
                    'name' => $settings['name'] ?? $feature->name,
                ]);

                // Update config with defaults if provided
                $config = $feature->config ?? [];
                if (isset($settings['default_enabled'])) {
                    $config['default_enabled'] = $settings['default_enabled'];
                }
                if (isset($settings['default_included_quantity'])) {
                    $config['default_included_quantity'] = $settings['default_included_quantity'];
                }
                if (isset($settings['default_overage_limit'])) {
                    $config['default_overage_limit'] = $settings['default_overage_limit'];
                }
                $feature->update(['config' => $config]);
            }
        }

        session()->flash('message', 'Feature catalog defaults saved successfully.');
        $this->loadCatalogFeatures(); // Reload to reflect changes
    }

    public function updatedActiveTab(): void
    {
        // Reload features when switching to features tab
        if ($this->activeTab === 'features') {
            $this->loadCatalogFeatures();
        }
    }

    protected function loadCatalogFeatures(): void
    {
        $this->catalogFeatureSettings = [];

        $features = ProductFeature::orderBy('key')->get();

        foreach ($features as $feature) {
            $config = $feature->config ?? [];
            $this->catalogFeatureSettings[$feature->id] = [
                'key' => $feature->key,
                'name' => $feature->name,
                'type' => $feature->type,
                'default_enabled' => $config['default_enabled'] ?? false,
                'default_included_quantity' => $config['default_included_quantity'] ?? null,
                'default_overage_limit' => $config['default_overage_limit'] ?? null,
            ];
        }
    }

    public function saveStorefrontSettings(): void
    {
        // Update products with storefront settings
        foreach ($this->storefrontPlans as $productId => $settings) {
            $product = Product::find($productId);
            if ($product) {
                $metadata = $product->metadata ?? [];
                $metadata['storefront']['plan']['show'] = $settings['show'] ?? false;
                $metadata['storefront']['plan']['recommended'] = ($this->recommendedPlanProductId === $productId);
                $product->update(['metadata' => $metadata]);
            }
        }

        foreach ($this->storefrontAddons as $productId => $settings) {
            $product = Product::find($productId);
            if ($product) {
                $metadata = $product->metadata ?? [];
                $metadata['storefront']['addon']['show'] = $settings['show'] ?? false;
                $product->update(['metadata' => $metadata]);
            }
        }

        session()->flash('message', 'Storefront settings saved successfully.');
    }

    protected function loadStorefrontSettings(): void
    {
        // Load recurring products (plans)
        $recurringProducts = Product::whereHas('prices', function ($query) {
            $query->where('type', \LaravelCatalog\Models\Price::TYPE_RECURRING);
        })->get();

        foreach ($recurringProducts as $product) {
            $metadata = $product->metadata ?? [];
            $this->storefrontPlans[$product->id] = [
                'show' => (bool) data_get($metadata, 'storefront.plan.show', false),
            ];

            if (data_get($metadata, 'storefront.plan.recommended', false)) {
                $this->recommendedPlanProductId = $product->id;
            }
        }

        // Load one-time products (add-ons)
        $oneTimeProducts = Product::whereHas('prices', function ($query) {
            $query->where('type', \LaravelCatalog\Models\Price::TYPE_ONE_TIME);
        })->get();

        foreach ($oneTimeProducts as $product) {
            $metadata = $product->metadata ?? [];
            $this->storefrontAddons[$product->id] = [
                'show' => (bool) data_get($metadata, 'storefront.addon.show', false),
            ];
        }
    }

    public function removeMetadata(int $index): void
    {
        unset($this->productMetadataKeys[$index]);
        unset($this->productMetadataValues[$index]);
        $this->productMetadataKeys = array_values($this->productMetadataKeys);
        $this->productMetadataValues = array_values($this->productMetadataValues);
    }

    protected function resetProductForm(): void
    {
        $this->productName = '';
        $this->productDescription = null;
        $this->productActive = true;
        $this->productTab = 'details';
        $this->editingProductId = null;
        $this->productImages = [];
        $this->productMetadataKeys = [];
        $this->productMetadataValues = [];
    }
}
