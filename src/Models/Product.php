<?php

namespace LaravelCatalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LaravelCatalog\Models\ProductFeature;

/**
 * Product model
 * Created to mirror Stripe's Product model for catalog management.
 * Products are containers that can have multiple Prices (monthly/yearly, add-ons).
 * Uses soft deletes to preserve financial history (invoices, transactions, payments).
 */
class Product extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'products';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \LaravelCatalog\Database\Factories\ProductFactory::new();
    }

    /**
     * Features that are attached to this billing product (FMS catalog).
     * Why: Used by the Feature Management System (FMS) and admin UI to
     * configure boolean and resource-based entitlements per product.
     */
    public function productFeatures(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ProductFeature::class, 'product_feature_configs')
            ->withPivot([
                'enabled',
                'included_quantity',
                'overage_limit',
                'config',
            ])
            ->withTimestamps();
    }

    /**
     * Mass assignable attributes matching Stripe Product structure.
     */
    protected $fillable = [
        'name',
        'description',
        'active',
        'images',
        'metadata',
        'statement_descriptor',
        'unit_label',
        'external_id',
        'lookup_key',
        'order',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'images' => 'array',
            'metadata' => 'array',
            'lookup_key' => 'string',
            'last_synced_at' => 'datetime',
            'order' => 'integer',
        ];
    }

    /**
     * Prices associated with this product.
     * Why: One Product can have multiple Prices (monthly/yearly, add-ons, one-time purchases).
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * Active prices for this product, ordered for display.
     */
    public function activePrices(): HasMany
    {
        return $this->prices()->where('active', true)->orderBy('order');
    }

    /**
     * Scope for products that are active, ordered for selection/display.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('order');
    }

    /**
     * Helper: metadata array with sensible default.
     */
    public function metadata(): array
    {
        return $this->metadata ?? [];
    }

    /**
     * Storefront configuration slice from metadata.
     * Why: Central place to read/write storefront flags (plan visibility,
     * recommended plan, and addon availability) without scattering JSON keys.
     */
    public function storefrontConfig(): array
    {
        return $this->metadata()['storefront'] ?? [];
    }

    /**
     * Whether this product should appear as a main plan on the public pricing page.
     */
    public function isStorefrontPlan(): bool
    {
        return (bool) data_get($this->metadata(), 'storefront.plan.show', false);
    }

    /**
     * Whether this product is marked as the recommended storefront plan.
     */
    public function isRecommendedPlan(): bool
    {
        return (bool) data_get($this->metadata(), 'storefront.plan.recommended', false);
    }

    /**
     * Whether this product should be listed as an optional add-on in storefront.
     */
    public function isStorefrontAddon(): bool
    {
        return (bool) data_get($this->metadata(), 'storefront.addon.show', false);
    }

    /**
     * Stripe product ID accessor.
     */
    public function stripeProductId(): ?string
    {
        return $this->external_id;
    }

    /**
     * Product lookup key accessor.
     * Why: Provides a stable, human-readable identifier that can be used instead of opaque ULIDs.
     */
    public function lookupKey(): ?string
    {
        return $this->lookup_key;
    }

    /**
     * Check if product is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Determine if this product or any of its prices are out of sync with Stripe.
     * Why: Used by the admin UI to surface an 'out of sync' warning.
     */
    public function isOutOfSync(): bool
    {
        if (! $this->last_synced_at) {
            return true;
        }

        if ($this->updated_at->gt($this->last_synced_at)) {
            return true;
        }

        foreach ($this->prices as $price) {
            if (! $price->last_synced_at || $price->updated_at->gt($price->last_synced_at)) {
                return true;
            }
        }

        return false;
    }
}

