<?php

namespace LaravelCatalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * ProductFeature model
 * Why: Represents a catalog of billable product features (boolean and resource-based)
 * that can be attached to Products and evaluated by the Feature Management System (FMS).
 */
class ProductFeature extends Model
{
    use HasFactory, HasUlids;

    /**
     * Resolve the table name from config (default `product_features`).
     * `??` so an explicit null still falls back.
     */
    public function getTable(): string
    {
        return config('catalog.tables.product_features') ?? 'product_features';
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \LaravelCatalog\Database\Factories\ProductFeatureFactory::new();
    }

    /**
     * Mass assignable attributes for admin configuration.
     */
    protected $fillable = [
        'key',
        'name',
        'description',
        'type',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /**
     * Products that include this feature.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, config('catalog.tables.product_feature_configs') ?? 'product_feature_configs')
            ->withPivot([
                'enabled',
                'included_quantity',
                'overage_limit',
                'config',
            ])
            ->withTimestamps();
    }
}

