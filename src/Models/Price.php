<?php

namespace LaravelCatalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LaravelCatalog\Database\Factories\PriceFactory;

/**
 * Price model
 * Created to mirror Stripe's Price model for catalog management.
 * Prices define the actual pricing (amount, currency, interval) and belong to Products.
 * Uses soft deletes to mirror Stripe's archiving behavior (prices are never truly deleted).
 * Pricing models are encoded to support Stripe's advanced pricing options (flat, tiered, usage-based, etc.).
 */
class Price extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \LaravelCatalog\Database\Factories\PriceFactory::new();
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'prices';

    public const TYPE_RECURRING = 'recurring';

    public const TYPE_ONE_TIME = 'one_time';

    /**
     * Supported pricing models.
     * Why: Encodes the Stripe-style pricing configuration for this price so we can map into Stripe fields.
     */
    public const PRICING_MODEL_FLAT_RECURRING = 'flat_recurring';

    public const PRICING_MODEL_PER_SEAT_RECURRING = 'per_seat_recurring';

    public const PRICING_MODEL_TIERED_RECURRING = 'tiered_recurring';

    public const PRICING_MODEL_USAGE_RECURRING = 'usage_recurring';

    public const PRICING_MODEL_FLAT_ONE_TIME = 'flat_one_time';

    public const PRICING_MODEL_PACKAGE_ONE_TIME = 'package_one_time';

    public const PRICING_MODEL_CUSTOMER_CHOICE_ONE_TIME = 'customer_choice_one_time';

    /**
     * Mass assignable attributes matching Stripe Price structure.
     */
    protected $fillable = [
        'product_id',
        'active',
        'currency',
        'unit_amount',
        'recurring_interval',
        'recurring_interval_count',
        'recurring_trial_period_days',
        'type',
        'pricing_model',
        'billing_scheme',
        'tiers',
        'tiers_mode',
        'transform_quantity',
        'custom_unit_amount',
        'nickname',
        'lookup_key',
        'metadata',
        'external_id',
        'order',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'unit_amount' => 'integer',
            'recurring_interval_count' => 'integer',
            'recurring_trial_period_days' => 'integer',
            'pricing_model' => 'string',
            'tiers' => 'array',
            'transform_quantity' => 'array',
            'custom_unit_amount' => 'array',
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
            'order' => 'integer',
        ];
    }

    /**
     * Product this price belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope for prices that are active, ordered for selection/display.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('order');
    }

    /**
     * Scope for recurring prices.
     */
    public function scopeRecurring($query)
    {
        return $query->where('type', self::TYPE_RECURRING);
    }

    /**
     * Scope for one-time prices.
     */
    public function scopeOneTime($query)
    {
        return $query->where('type', self::TYPE_ONE_TIME);
    }

    /**
     * Helper: metadata array with sensible default.
     */
    public function metadata(): array
    {
        return $this->metadata ?? [];
    }

    /**
     * Stripe price ID accessor.
     */
    public function stripePriceId(): ?string
    {
        return $this->external_id;
    }

    /**
     * Shared internal price identifier used to link multiple Stripe Price versions.
     * Why: Stripe archives old Price objects and creates new ones; this ULID stays stable across versions.
     */
    public function sharedPriceId(): string
    {
        return (string) $this->id;
    }

    /**
     * Check if price is recurring.
     */
    public function isRecurring(): bool
    {
        return $this->type === self::TYPE_RECURRING;
    }

    /**
     * Check if price is one-time.
     */
    public function isOneTime(): bool
    {
        return $this->type === self::TYPE_ONE_TIME;
    }

    /**
     * Get trial period days (for recurring prices).
     */
    public function trialDays(): ?int
    {
        return $this->recurring_trial_period_days;
    }

    /**
     * Get recurring interval (month, year, etc.).
     */
    public function interval(): ?string
    {
        return $this->recurring_interval;
    }

    /**
     * Get amount in cents.
     */
    public function amountCents(): int
    {
        return $this->unit_amount;
    }

    /**
     * Seats included in this price (from metadata).
     */
    public function seatsIncluded(): int
    {
        return (int) ($this->metadata()['seats_included'] ?? 0);
    }

    /**
     * Tokens allocated per billing period for this price.
     */
    public function tokensPerPeriod(): int
    {
        return (int) ($this->metadata()['tokens_per_period'] ?? 0);
    }

    /**
     * MCP calls allocated per billing period for this price.
     */
    public function mcpCallsPerPeriod(): int
    {
        return (int) ($this->metadata()['mcp_calls_per_period'] ?? 0);
    }
}

