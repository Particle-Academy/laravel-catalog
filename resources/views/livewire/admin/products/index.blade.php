@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/catalog/admin.css') }}">
@endpush

<div wire:poll.15s="$refresh" class="catalog-admin-products">
    {{-- Page Header --}}
    <div class="catalog-mb-4">
        <h1 class="catalog-heading catalog-heading-lg">Products</h1>
        <p class="catalog-text catalog-text-sm catalog-text-muted">Manage Stripe catalog: Products and their Prices</p>
    </div>

    {{-- Navigation Tabs --}}
    <div class="catalog-tabs catalog-mb-4">
        @php
            $baseRoute = config('catalog.admin_route_names.products_index', 'admin.products.index');
        @endphp
        <a href="{{ route($baseRoute, ['tab' => 'plans']) }}" 
           class="catalog-tab {{ $activeTab === 'plans' ? 'catalog-tab-active' : '' }}"
           wire:navigate>
            Plans
        </a>
        <a href="{{ route($baseRoute, ['tab' => 'products']) }}" 
           class="catalog-tab {{ $activeTab === 'products' ? 'catalog-tab-active' : '' }}"
           wire:navigate>
            Products
        </a>
        <a href="{{ route($baseRoute, ['tab' => 'features']) }}" 
           class="catalog-tab {{ $activeTab === 'features' ? 'catalog-tab-active' : '' }}"
           wire:navigate>
            Features
        </a>
        <a href="{{ route($baseRoute, ['tab' => 'settings']) }}" 
           class="catalog-tab {{ $activeTab === 'settings' ? 'catalog-tab-active' : '' }}"
           wire:navigate>
            Settings
        </a>
    </div>

    {{-- Action Buttons --}}
    <div class="catalog-flex catalog-items-center catalog-justify-between catalog-mb-4">
        <div class="catalog-flex catalog-items-center catalog-gap-2">
            @if($activeTab === 'plans' || $activeTab === 'products')
                <button type="button" class="catalog-btn catalog-btn-primary catalog-btn-sm" wire:click="openCreateProduct">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    {{ $activeTab === 'plans' ? 'New Plan' : 'New Product' }}
                </button>
            @endif

            <button type="button" class="catalog-btn catalog-btn-ghost catalog-btn-sm" 
                    wire:click="syncAllProducts"
                    wire:loading.attr="disabled"
                    wire:target="syncAllProducts">
                <span wire:loading.remove wire:target="syncAllProducts">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Sync all to Stripe
                </span>
                <span wire:loading wire:target="syncAllProducts" class="catalog-flex catalog-items-center catalog-gap-2">
                    <span class="catalog-spinner"></span>
                    Syncing...
                </span>
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="catalog-callout catalog-callout-red catalog-mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if(session('message'))
        <div class="catalog-callout catalog-callout-green catalog-mb-4">
            {{ session('message') }}
        </div>
    @endif

    {{-- Plans Tab (Recurring) --}}
    @if($activeTab === 'plans')
        <div class="catalog-card">
            <table class="catalog-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Plans</th>
                        <th>Status</th>
                        <th class="catalog-text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</div>
                            </td>
                            <td>
                                @if($product->description)
                                    <div class="catalog-text-sm catalog-text-muted">{{ Str::limit($product->description, 50) }}</div>
                                @else
                                    <span class="catalog-text-sm catalog-text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="catalog-text-sm">{{ $product->prices->count() }} {{ $product->prices->count() === 1 ? 'plan' : 'plans' }}</span>
                            </td>
                            <td>
                                @if($product->active)
                                    <span class="catalog-badge catalog-badge-green">Active</span>
                                @else
                                    <span class="catalog-badge catalog-badge-gray">Inactive</span>
                                @endif

                                <div class="mt-1 catalog-text-xs catalog-text-muted">
                                    @if($product->isOutOfSync())
                                        <span class="text-amber-600 dark:text-amber-400">Out of sync with Stripe</span>
                                    @elseif($product->last_synced_at)
                                        <span>Synced {{ $product->last_synced_at->diffForHumans() }}</span>
                                    @else
                                        <span>Not yet synced</span>
                                    @endif
                                </div>
                            </td>
                            <td class="catalog-text-right">
                                <div class="catalog-flex catalog-items-center catalog-justify-end catalog-gap-2">
                                    <button type="button" class="catalog-btn catalog-btn-ghost catalog-btn-sm"
                                            wire:click="syncProductNow('{{ $product->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="syncProductNow">
                                        <span wire:loading.remove wire:target="syncProductNow">Sync</span>
                                        <span wire:loading wire:target="syncProductNow" class="catalog-flex catalog-items-center catalog-gap-2">
                                            <span class="catalog-spinner"></span>
                                            Queued...
                                        </span>
                                    </button>
                                    <button type="button" class="catalog-btn catalog-btn-ghost catalog-btn-sm" wire:click="openEditProduct('{{ $product->id }}')">
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="catalog-text-center catalog-text-muted">No plans found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="catalog-mt-4">
                {{ $products->links() }}
            </div>
        </div>
    @endif

    {{-- Products Tab (One-time) --}}
    @if($activeTab === 'products')
        <div class="catalog-card">
            <table class="catalog-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Prices</th>
                        <th>Status</th>
                        <th class="catalog-text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</div>
                            </td>
                            <td>
                                @if($product->description)
                                    <div class="catalog-text-sm catalog-text-muted">{{ Str::limit($product->description, 50) }}</div>
                                @else
                                    <span class="catalog-text-sm catalog-text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="catalog-text-sm">{{ $product->prices->count() }} {{ $product->prices->count() === 1 ? 'price' : 'prices' }}</span>
                            </td>
                            <td>
                                @if($product->active)
                                    <span class="catalog-badge catalog-badge-green">Active</span>
                                @else
                                    <span class="catalog-badge catalog-badge-gray">Inactive</span>
                                @endif

                                <div class="mt-1 catalog-text-xs catalog-text-muted">
                                    @if($product->isOutOfSync())
                                        <span class="text-amber-600 dark:text-amber-400">Out of sync with Stripe</span>
                                    @elseif($product->last_synced_at)
                                        <span>Synced {{ $product->last_synced_at->diffForHumans() }}</span>
                                    @else
                                        <span>Not yet synced</span>
                                    @endif
                                </div>
                            </td>
                            <td class="catalog-text-right">
                                <div class="catalog-flex catalog-items-center catalog-justify-end catalog-gap-2">
                                    <button type="button" class="catalog-btn catalog-btn-ghost catalog-btn-sm"
                                            wire:click="syncProductNow('{{ $product->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="syncProductNow">
                                        <span wire:loading.remove wire:target="syncProductNow">Sync</span>
                                        <span wire:loading wire:target="syncProductNow" class="catalog-flex catalog-items-center catalog-gap-2">
                                            <span class="catalog-spinner"></span>
                                            Queued...
                                        </span>
                                    </button>
                                    <button type="button" class="catalog-btn catalog-btn-ghost catalog-btn-sm" wire:click="openEditProduct('{{ $product->id }}')">
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="catalog-text-center catalog-text-muted">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="catalog-mt-4">
                {{ $products->links() }}
            </div>
        </div>
    @endif

    {{-- Features Tab --}}
    @if($activeTab === 'features')
        <div class="catalog-card">
            <div class="catalog-flex catalog-items-center catalog-justify-between catalog-mb-4">
                <div>
                    <h2 class="catalog-heading catalog-heading-sm">Feature Catalog</h2>
                    <p class="catalog-text catalog-text-sm catalog-text-muted">
                        Configure system-wide defaults for FMS product features.
                    </p>
                </div>
                <button type="button" class="catalog-btn catalog-btn-primary catalog-btn-sm" 
                        wire:click="saveCatalogFeatures" 
                        wire:loading.attr="disabled">
                    Save defaults
                </button>
            </div>

            <table class="catalog-table">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Default Enabled</th>
                        <th>Defaults</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($catalogFeatureSettings as $featureId => $feature)
                        <tr>
                            <td class="font-mono catalog-text-xs">
                                {{ $feature['key'] ?? $featureId }}
                            </td>
                            <td>
                                <input type="text" 
                                       class="catalog-input catalog-input-sm"
                                       wire:model="catalogFeatureSettings.{{ $featureId }}.name" />
                            </td>
                            <td>
                                @php
                                    $featureType = $feature['type'] ?? 'boolean';
                                    $isResource = $featureType === 'resource';
                                @endphp
                                <span class="catalog-badge catalog-badge-xs {{ $isResource ? 'catalog-badge-blue' : 'catalog-badge-gray' }}">
                                    {{ ucfirst($featureType) }}
                                </span>
                            </td>
                            <td>
                                <label class="catalog-switch">
                                    <input type="checkbox" 
                                           wire:model.live="catalogFeatureSettings.{{ $featureId }}.default_enabled" />
                                    <span class="catalog-switch-slider"></span>
                                </label>
                            </td>
                            <td>
                                @if($isResource)
                                    <div class="catalog-flex catalog-gap-3">
                                        <div class="catalog-field">
                                            <label class="catalog-label catalog-text-xs">Included</label>
                                            <input type="number" 
                                                   min="0" 
                                                   class="catalog-input w-24"
                                                   wire:model="catalogFeatureSettings.{{ $featureId }}.default_included_quantity" />
                                        </div>
                                        <div class="catalog-field">
                                            <label class="catalog-label catalog-text-xs">Overage</label>
                                            <input type="number" 
                                                   min="0" 
                                                   class="catalog-input w-24"
                                                   wire:model="catalogFeatureSettings.{{ $featureId }}.default_overage_limit" />
                                        </div>
                                    </div>
                                @else
                                    <span class="catalog-text-xs catalog-text-muted">No limits for boolean features.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="catalog-text-center catalog-text-muted">
                                No Product Features found. Define them in code to configure catalog defaults.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Settings Tab --}}
    @if($activeTab === 'settings')
        <div class="catalog-space-y-6">
            {{-- Storefront Plans --}}
            <div class="catalog-card">
                <div class="catalog-flex catalog-items-center catalog-justify-between catalog-mb-4">
                    <div>
                        <h2 class="catalog-heading catalog-heading-sm">Storefront Plans</h2>
                        <p class="catalog-text catalog-text-sm catalog-text-muted">
                            Choose which recurring products appear on the public Plans page and which one is recommended.
                        </p>
                    </div>
                    <button type="button" class="catalog-btn catalog-btn-primary catalog-btn-sm" 
                            wire:click="saveStorefrontSettings" 
                            wire:loading.attr="disabled">
                        Save storefront settings
                    </button>
                </div>

                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Visibility</th>
                            <th>Recommended</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $allProducts = ($allProducts ?? $products); @endphp
                        @forelse($storefrontPlans as $productId => $settings)
                            @php $product = $allProducts->firstWhere('id', $productId); @endphp
                            @if($product)
                                <tr>
                                    <td>
                                        <div class="catalog-flex catalog-flex-col catalog-text-sm">
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</span>
                                            <span class="catalog-text-xs catalog-text-muted">{{ $product->description }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <label class="catalog-switch">
                                            <input type="checkbox" 
                                                   wire:model.live="storefrontPlans.{{ $productId }}.show" />
                                            <span class="catalog-switch-slider"></span>
                                        </label>
                                        <span class="catalog-text-xs catalog-text-muted ml-2">Show on Plans page</span>
                                    </td>
                                    <td>
                                        <label class="catalog-flex catalog-items-center catalog-gap-2 catalog-text-xs catalog-text-muted">
                                            <input type="radio" 
                                                   class="accent-blue-500 dark:accent-blue-400"
                                                   wire:model="recommendedPlanProductId"
                                                   value="{{ $productId }}" />
                                            <span>Recommended plan</span>
                                        </label>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="3" class="catalog-text-center catalog-text-muted catalog-text-sm">
                                    No recurring products found. Create a plan in the Plans tab first.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Storefront Add-ons --}}
            <div class="catalog-card">
                <div class="catalog-mb-4">
                    <h2 class="catalog-heading catalog-heading-sm">Storefront Add-ons</h2>
                    <p class="catalog-text catalog-text-sm catalog-text-muted">
                        Control which one-time products are offered as optional add-ons.
                    </p>
                </div>

                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th>Add-on</th>
                            <th>Visibility</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($storefrontAddons as $productId => $settings)
                            @php $product = $allProducts->firstWhere('id', $productId); @endphp
                            @if($product)
                                <tr>
                                    <td>
                                        <div class="catalog-flex catalog-flex-col catalog-text-sm">
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</span>
                                            <span class="catalog-text-xs catalog-text-muted">{{ $product->description }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <label class="catalog-switch">
                                            <input type="checkbox" 
                                                   wire:model.live="storefrontAddons.{{ $productId }}.show" />
                                            <span class="catalog-switch-slider"></span>
                                        </label>
                                        <span class="catalog-text-xs catalog-text-muted ml-2">Show as add-on</span>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="2" class="catalog-text-center catalog-text-muted catalog-text-sm">
                                    No one-time products found. Create products with one-time prices in the Products tab.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Product Modal --}}
    @if($showCreateProduct)
        <div class="catalog-modal-overlay" wire:click="$set('showCreateProduct', false)">
            <div class="catalog-modal-flyout" wire:click.stop>
                <div class="catalog-modal-header">
                    <h2 class="catalog-heading catalog-heading-lg">{{ $editingProductId ? 'Edit Product' : 'Add a product' }}</h2>
                    <button type="button" class="catalog-btn catalog-btn-ghost catalog-btn-sm" wire:click="$set('showCreateProduct', false)" aria-label="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="catalog-modal-body">
                    {{-- Tabs --}}
                    <div class="catalog-tabs catalog-mb-4">
                        <button type="button" 
                                class="catalog-tab {{ $productTab === 'details' ? 'catalog-tab-active' : '' }}"
                                wire:click="$set('productTab', 'details')">
                            Details
                        </button>
                        <button type="button" 
                                class="catalog-tab {{ $productTab === 'pricing' ? 'catalog-tab-active' : '' }}"
                                wire:click="$set('productTab', 'pricing')">
                            Pricing
                        </button>
                    </div>

                    {{-- Details Tab --}}
                    @if($productTab === 'details')
                        <div class="catalog-space-y-6">
                            <div class="catalog-field">
                                <label class="catalog-label">Name <span class="text-red-500 dark:text-red-400">*</span></label>
                                <input type="text" 
                                       class="catalog-input" 
                                       wire:model="productName" 
                                       placeholder="e.g., Pro Plan" />
                                @error('productName') <span class="catalog-error">{{ $message }}</span> @enderror
                                <span class="catalog-description">Name of the product or service, visible to customers.</span>
                            </div>

                            <div class="catalog-field">
                                <label class="catalog-label">Description</label>
                                <textarea class="catalog-textarea" 
                                          wire:model="productDescription" 
                                          rows="4" 
                                          placeholder="Describe what this product includes..."></textarea>
                                @error('productDescription') <span class="catalog-error">{{ $message }}</span> @enderror
                                <span class="catalog-description">Appears at checkout, on the customer portal, and in quotes.</span>
                            </div>

                            <div class="catalog-field">
                                <label class="catalog-label">Image</label>
                                <div class="catalog-space-y-2">
                                    @forelse($productImages as $index => $image)
                                        <div class="catalog-flex catalog-items-center catalog-gap-2">
                                            <input type="url" 
                                                   class="catalog-input flex-1" 
                                                   wire:model="productImages.{{ $index }}" 
                                                   placeholder="https://example.com/image.jpg" />
                                            <button type="button" 
                                                    class="catalog-btn catalog-btn-ghost catalog-btn-sm" 
                                                    wire:click="removeImage({{ $index }})">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @empty
                                        <button type="button" 
                                                class="catalog-btn catalog-btn-ghost catalog-btn-sm" 
                                                wire:click="addImage">
                                            Upload
                                        </button>
                                    @endforelse
                                    @if(count($productImages) > 0 && count($productImages) < 8)
                                        <button type="button" 
                                                class="catalog-btn catalog-btn-ghost catalog-btn-sm" 
                                                wire:click="addImage">
                                            Add another image
                                        </button>
                                    @endif
                                </div>
                                @error('productImages') <span class="catalog-error">{{ $message }}</span> @enderror
                                <span class="catalog-description">Appears at checkout. JPEG, PNG, or WEBP under 2MB.</span>
                            </div>

                            {{-- More Options --}}
                            <details class="group">
                                <summary class="cursor-pointer catalog-text-sm font-medium">More options</summary>
                                <div class="catalog-mt-4 catalog-space-y-4">
                                    <div class="catalog-field">
                                        <label class="catalog-switch">
                                            <input type="checkbox" wire:model.live="productActive" />
                                            <span class="catalog-switch-slider"></span>
                                        </label>
                                        <span class="catalog-label">Active</span>
                                        @error('productActive') <span class="catalog-error">{{ $message }}</span> @enderror
                                        <span class="catalog-description">Whether this product is available for purchase</span>
                                    </div>

                                    <div class="catalog-field">
                                        <label class="catalog-label">Statement Descriptor</label>
                                        <input type="text" 
                                               class="catalog-input" 
                                               wire:model="productStatementDescriptor" 
                                               maxlength="22" 
                                               placeholder="e.g., PRO PLAN" />
                                        @error('productStatementDescriptor') <span class="catalog-error">{{ $message }}</span> @enderror
                                        <span class="catalog-description">Appears on customer's credit card statement (max 22 characters)</span>
                                    </div>

                                    <div class="catalog-field">
                                        <label class="catalog-label">Unit Label</label>
                                        <input type="text" 
                                               class="catalog-input" 
                                               wire:model="productUnitLabel" 
                                               maxlength="12" 
                                               placeholder="e.g., seat, license" />
                                        @error('productUnitLabel') <span class="catalog-error">{{ $message }}</span> @enderror
                                        <span class="catalog-description">Label for the unit of measurement (max 12 characters)</span>
                                    </div>

                                    <div class="catalog-field">
                                        <label class="catalog-label">Display Order</label>
                                        <input type="number" 
                                               class="catalog-input" 
                                               wire:model="productOrder" 
                                               min="0" />
                                        @error('productOrder') <span class="catalog-error">{{ $message }}</span> @enderror
                                        <span class="catalog-description">Lower numbers appear first in listings</span>
                                    </div>

                                    <div class="catalog-field">
                                        <label class="catalog-label">Metadata</label>
                                        <div class="catalog-space-y-2">
                                            @forelse($productMetadataKeys as $index => $key)
                                                <div class="grid grid-cols-2 catalog-gap-2">
                                                    <input type="text" 
                                                           class="catalog-input" 
                                                           wire:model="productMetadataKeys.{{ $index }}" 
                                                           placeholder="Key" />
                                                    <div class="catalog-flex catalog-items-center catalog-gap-2">
                                                        <input type="text" 
                                                               class="catalog-input" 
                                                               wire:model="productMetadataValues.{{ $index }}" 
                                                               placeholder="Value" />
                                                        <button type="button" 
                                                                class="catalog-btn catalog-btn-ghost catalog-btn-sm" 
                                                                wire:click="removeMetadata({{ $index }})">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            @empty
                                                <span class="catalog-text catalog-text-sm catalog-text-muted">No metadata added</span>
                                            @endforelse
                                            <button type="button" 
                                                    class="catalog-btn catalog-btn-ghost catalog-btn-sm" 
                                                    wire:click="addMetadata">
                                                Add Metadata
                                            </button>
                                        </div>
                                        <span class="catalog-description">Custom key-value pairs for storing additional data</span>
                                    </div>
                                </div>
                            </details>
                        </div>
                    @endif

                    {{-- Pricing Tab --}}
                    @if($productTab === 'pricing')
                        <div class="catalog-space-y-4">
                            <div class="catalog-flex catalog-items-center catalog-justify-between">
                                <div>
                                    <h3 class="catalog-heading catalog-heading-sm">Pricing</h3>
                                    <p class="catalog-text catalog-text-sm catalog-text-muted">
                                        Manage all prices for this product. A product can have multiple active prices (monthly, yearly, usage-based, etc.).
                                    </p>
                                </div>
                                <button type="button" 
                                        class="catalog-btn catalog-btn-primary catalog-btn-sm" 
                                        wire:click="startCreatePriceDraft">
                                    Add price
                                </button>
                            </div>

                            @forelse($productPrices as $index => $price)
                                <div class="catalog-flex catalog-items-center catalog-justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-4 py-3 catalog-text-sm">
                                    <div class="catalog-flex catalog-flex-col catalog-gap-1">
                                        <div class="catalog-flex catalog-items-center catalog-gap-2">
                                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                                ${{ number_format($price['unit_amount_dollars'], 2) }} {{ $price['currency'] }}
                                            </span>
                                            @if(($price['type'] ?? null) === \LaravelCatalog\Models\Price::TYPE_RECURRING)
                                                <span class="catalog-badge catalog-badge-xs catalog-badge-blue">Recurring / {{ $price['recurring_interval'] }}</span>
                                            @else
                                                <span class="catalog-badge catalog-badge-xs catalog-badge-gray">One-off</span>
                                            @endif
                                            @if(!empty($price['lookup_key']))
                                                <span class="catalog-badge catalog-badge-xs catalog-badge-gray">lookup: {{ $price['lookup_key'] }}</span>
                                            @endif
                                        </div>
                                        <div class="catalog-flex catalog-gap-2 catalog-text-xs catalog-text-muted">
                                            @if(!empty($price['nickname']))
                                                <span>{{ $price['nickname'] }}</span>
                                            @endif
                                            <span>Billing scheme: {{ $price['billing_scheme'] ?? 'per_unit' }}</span>
                                            <span>Status: {{ !empty($price['active']) ? 'Active' : 'Inactive' }}</span>
                                        </div>
                                    </div>
                                    <div class="catalog-flex catalog-items-center catalog-gap-2">
                                        <button type="button" 
                                                class="catalog-btn catalog-btn-ghost catalog-btn-xs" 
                                                wire:click="startEditPriceDraft({{ $index }})">
                                            Edit
                                        </button>
                                        <button type="button" 
                                                class="catalog-btn catalog-btn-ghost catalog-btn-xs" 
                                                wire:click="removePriceDraft({{ $index }})">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700 p-6 catalog-text-center">
                                    <span class="catalog-text catalog-text-sm catalog-text-muted">
                                        No prices yet. Click "Add price" to create a pricing model for this product.
                                    </span>
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>

                <div class="catalog-modal-footer">
                    <div class="catalog-flex catalog-items-center catalog-justify-between catalog-gap-2">
                        <div>
                            @if($editingProductId)
                                <button type="button" 
                                        class="catalog-btn catalog-btn-ghost text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" 
                                        wire:click="confirmDeleteProduct"
                                        wire:loading.attr="disabled">
                                    Delete
                                </button>
                            @endif
                        </div>
                        <div class="catalog-flex catalog-items-center catalog-gap-2">
                            <button type="button" 
                                    class="catalog-btn catalog-btn-ghost" 
                                    wire:click="$set('showCreateProduct', false)" 
                                    wire:loading.attr="disabled">
                                Cancel
                            </button>
                            <button type="button" 
                                    class="catalog-btn catalog-btn-primary" 
                                    wire:click="saveProduct" 
                                    wire:loading.attr="disabled" 
                                    wire:target="saveProduct">
                                <span wire:loading.remove wire:target="saveProduct">{{ $editingProductId ? 'Save Product' : 'Add product' }}</span>
                                <span wire:loading wire:target="saveProduct" class="catalog-flex catalog-items-center catalog-gap-2">
                                    <span class="catalog-spinner"></span>
                                    Saving...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if($showDeleteConfirm)
        <div class="catalog-modal-overlay" wire:click="cancelDeleteProduct">
            <div class="catalog-modal" wire:click.stop style="max-width: 28rem;">
                <div class="catalog-modal-body">
                    <div class="catalog-space-y-4">
                        <h2 class="catalog-heading catalog-heading-lg">Delete Product</h2>
                        <p class="catalog-text">
                            Are you sure you want to delete "{{ $productName }}"? This action cannot be undone. All associated prices will also be archived.
                        </p>
                        <div class="catalog-flex catalog-justify-end catalog-gap-2">
                            <button type="button" 
                                    class="catalog-btn catalog-btn-ghost" 
                                    wire:click="cancelDeleteProduct" 
                                    wire:loading.attr="disabled">
                                Cancel
                            </button>
                            <button type="button" 
                                    class="catalog-btn catalog-btn-danger" 
                                    wire:click="deleteProduct" 
                                    wire:loading.attr="disabled" 
                                    wire:target="deleteProduct">
                                <span wire:loading.remove wire:target="deleteProduct">Delete</span>
                                <span wire:loading wire:target="deleteProduct" class="catalog-flex catalog-items-center catalog-gap-2">
                                    <span class="catalog-spinner"></span>
                                    Deleting...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Note: Price Modal and Pricing Model Modal are complex and would require the Livewire component class to be available --}}
    {{-- These will need to be completed once the component class structure is confirmed --}}
</div>

