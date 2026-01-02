<?php

namespace LaravelCatalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
     * The table associated with the model.
     */
    protected $table = 'product_features';

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
        return $this->belongsToMany(Product::class, 'product_feature_configs')
            ->withPivot([
                'enabled',
                'included_quantity',
                'overage_limit',
                'config',
            ])
            ->withTimestamps();
    }
}

