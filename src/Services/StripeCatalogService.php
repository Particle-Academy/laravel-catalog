<?php

namespace LaravelCatalog\Services;

use LaravelCatalog\Models\Price;
use LaravelCatalog\Models\Product;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * StripeCatalogService
 * Created to sync Products and Prices to Stripe's catalog using the stripe-php SDK.
 * Provides full catalog management from our app without needing Stripe dashboard.
 */
class StripeCatalogService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(['api_key' => config('cashier.secret')]);
    }

    /**
     * Sync a Product to Stripe.
     * Creates or updates the Stripe Product and saves the external_id.
     */
    public function syncProduct(Product $product): Product
    {
        try {
            $stripeProductData = [
                'name' => $product->name,
                'active' => $product->active,
                'metadata' => array_merge($product->metadata() ?? [], [
                    'product_id' => $product->id,
                    // Store lookup_key in metadata because Stripe Products do not support lookup keys directly.
                    'product_lookup_key' => $product->lookupKey(),
                ]),
            ];

            // Only include description if it's not null and not empty
            if (! empty($product->description)) {
                $stripeProductData['description'] = $product->description;
            }

            if ($product->statement_descriptor) {
                $stripeProductData['statement_descriptor'] = $product->statement_descriptor;
            }

            if ($product->unit_label) {
                $stripeProductData['unit_label'] = $product->unit_label;
            }

            if ($product->images && count($product->images) > 0) {
                $stripeProductData['images'] = $product->images;
            }

            if ($product->external_id) {
                // Update existing Stripe product
                $stripeProduct = $this->stripe->products->update($product->external_id, $stripeProductData);
            } else {
                // Create new Stripe product
                $stripeProduct = $this->stripe->products->create($stripeProductData);
                $product->external_id = $stripeProduct->id;
                $product->save();
            }

            return $product;
        } catch (ApiErrorException $e) {
            // Log error and rethrow or handle gracefully
            Log::error('Stripe product sync failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Test Stripe connection by listing products.
     * Created to verify Stripe API authentication is working correctly.
     */
    public function testConnection(): array
    {
        try {
            $products = $this->stripe->products->all(['limit' => 10]);

            return [
                'success' => true,
                'message' => sprintf('Success! Connected to Stripe. Found %d product(s) in your Stripe account.', count($products->data)),
                'product_count' => count($products->data),
            ];
        } catch (\Throwable $e) {
            // Log the underlying Stripe error for operators, but return a
            // generic message to callers — raw API errors can leak account
            // identifiers, key prefixes, and other implementation details
            // that admins shouldn't see in the UI.
            Log::error('catalog.stripe.test_connection_failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Could not reach Stripe. Check your API credentials and try again.',
            ];
        }
    }

    /**
     * Sync a Price to Stripe.
     * Creates or updates the Stripe Price and saves the external_id.
     * Note: Stripe prices are immutable, so if pricing changes, a new price is created.
     */
    public function syncPrice(Price $price): Price
    {
        try {
            // Ensure product is synced first (refresh relationship to get latest external_id)
            $price->load('product');
            if (! $price->product->external_id) {
                $this->syncProduct($price->product);
                // Refresh price relationship to get updated product
                $price->refresh();
            }

            $stripePriceData = [
                'product' => $price->product->external_id,
                'currency' => strtolower($price->currency),
                'active' => $price->active,
                'metadata' => array_merge($price->metadata() ?? [], [
                    // Shared internal ID allowing us to link archived and replacement Stripe Prices.
                    'price_id' => $price->sharedPriceId(),
                    'product_id' => $price->product_id,
                    'lookup_key' => $price->lookup_key,
                ]),
            ];

            // Sent only when there IS one. Stripe sets no unit amount on a
            // `tiered` or `custom_unit_amount` price — the tiers carry the money
            // — and passing `unit_amount` alongside `tiers` is an API error.
            // Passing 0 instead would be worse: a free price, silently.
            if ($price->unit_amount !== null) {
                $stripePriceData['unit_amount'] = (int) $price->unit_amount;
            }

            // Stripe Prices support lookup_key NATIVELY, and the metadata copy
            // above is not a substitute: `prices.list(lookup_keys: [...])` only
            // reads the real field, which is the entire reason a lookup key
            // exists. Sent only when set — passing null would clear a key that
            // is already on the Stripe price.
            //
            // `transfer_lookup_key` is what makes this survive this service's
            // own lifecycle. Prices are immutable, so a changed amount archives
            // the old price and creates a new one below; without the transfer,
            // that create fails with "lookup key already exists" the first time
            // anyone changes the price of something that has a key.
            if ($price->lookup_key) {
                $stripePriceData['lookup_key'] = $price->lookup_key;
                $stripePriceData['transfer_lookup_key'] = true;
            }

            if ($price->billing_scheme) {
                $stripePriceData['billing_scheme'] = $price->billing_scheme;
            }

            if ($price->billing_scheme === 'tiered' && $price->tiers) {
                $stripePriceData['tiers'] = $price->tiers;
                if ($price->tiers_mode) {
                    $stripePriceData['tiers_mode'] = $price->tiers_mode;
                }
            }

            if ($price->transform_quantity) {
                $stripePriceData['transform_quantity'] = $price->transform_quantity;
            }

            if ($price->custom_unit_amount) {
                $stripePriceData['custom_unit_amount'] = $price->custom_unit_amount;
            }

            if ($price->type === Price::TYPE_RECURRING) {
                $stripePriceData['recurring'] = [
                    'interval' => $price->recurring_interval,
                    'interval_count' => $price->recurring_interval_count ?? 1,
                ];

                if ($price->recurring_trial_period_days) {
                    $stripePriceData['recurring']['trial_period_days'] = $price->recurring_trial_period_days;
                }

                // Usage-based pricing configuration
                if ($price->pricing_model === Price::PRICING_MODEL_USAGE_RECURRING) {
                    $stripePriceData['recurring']['usage_type'] = 'metered';
                } else {
                    $stripePriceData['recurring']['usage_type'] = 'licensed';
                }
            } else {
                $stripePriceData['type'] = 'one_time';
            }

            // Check if price already exists and if pricing has changed
            if ($price->external_id) {
                try {
                    $existingPrice = $this->stripe->prices->retrieve($price->external_id);

                    // Compare key fields to see if we need a new price
                    // Both sides normalised to null: Stripe returns
                    // `unit_amount: null` for a tiered price, and this side may
                    // hold null or an integer that arrived from JSON as a
                    // string. `!==` between null and 0 is a difference; between
                    // "1999" and 1999 it is also a difference, and either one
                    // archives a live price and churns its id for nothing.
                    $pricingChanged = ! self::sameAmount($existingPrice->unit_amount, $price->unit_amount)
                        || $existingPrice->currency !== strtolower($price->currency)
                        || ($price->type === Price::TYPE_RECURRING && (
                            $existingPrice->recurring->interval !== $price->recurring_interval
                            || $existingPrice->recurring->interval_count !== ($price->recurring_interval_count ?? 1)
                            || ($existingPrice->recurring->usage_type ?? 'licensed') !== ($stripePriceData['recurring']['usage_type'] ?? 'licensed')
                        ))
                        || ($existingPrice->billing_scheme ?? 'per_unit') !== ($stripePriceData['billing_scheme'] ?? 'per_unit')
                        || ($existingPrice->tiers_mode ?? null) !== ($stripePriceData['tiers_mode'] ?? null)
                        || json_encode($existingPrice->tiers ?? []) !== json_encode($stripePriceData['tiers'] ?? [])
                        // Compared order-INSENSITIVELY: these are maps, and the two sides
                        // come from different places -- Stripe returns its own key order,
                        // we build ours. A plain json_encode compare called identical
                        // pricing "changed", archiving a live price and creating a
                        // replacement, which churns the price id and orphans whatever
                        // referenced it. `tiers` above stays ordered: it is a list.
                        || ! self::sameShape($existingPrice->transform_quantity ?? [], $stripePriceData['transform_quantity'] ?? [])
                        || ! self::sameShape($existingPrice->custom_unit_amount ?? [], $stripePriceData['custom_unit_amount'] ?? []);

                    if ($pricingChanged) {
                        // Archive old price
                        $this->stripe->prices->update($price->external_id, ['active' => false]);

                        // Create new price (prices are immutable)
                        $stripePrice = $this->stripe->prices->create($stripePriceData);

                        $price->external_id = $stripePrice->id;
                        $price->save();
                    } else {
                        // Just update metadata/active status
                        $updates = [
                            'active' => $price->active,
                            'metadata' => $stripePriceData['metadata'],
                        ];

                        // Changing ONLY the lookup key leaves pricing untouched,
                        // so it lands here rather than in the archive-and-replace
                        // branch. Omitting it would make that edit a silent
                        // no-op: saved locally, never sent, and a lookup by the
                        // new key finds nothing.
                        if ($price->lookup_key) {
                            $updates['lookup_key'] = $price->lookup_key;
                            $updates['transfer_lookup_key'] = true;
                        }

                        $this->stripe->prices->update($price->external_id, $updates);
                    }
                } catch (ApiErrorException $e) {
                    // Price doesn't exist in Stripe, create it
                    $stripePrice = $this->stripe->prices->create($stripePriceData);
                    $price->external_id = $stripePrice->id;
                    $price->save();
                }
            } else {
                // Create new Stripe price
                $stripePrice = $this->stripe->prices->create($stripePriceData);
                $price->external_id = $stripePrice->id;
                $price->save();
            }

            return $price;
        } catch (ApiErrorException $e) {
            // Log error and rethrow or handle gracefully
            Log::error('Stripe price sync failed', [
                'price_id' => $price->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync a Product and all its Prices to Stripe.
     */
    public function syncProductAndPrices(Product $product): Product
    {
        // Sync product first
        $this->syncProduct($product);

        // Sync all prices for this product
        foreach ($product->prices as $price) {
            $this->syncPrice($price);
        }

        return $product->fresh();
    }

    /**
     * Do two unit amounts mean the same money?
     *
     * `null` is a real value here — a `tiered` or `custom_unit_amount` price has
     * no unit amount, and Stripe returns null for it — so `null` and `0` must
     * compare as DIFFERENT: one is "the tiers carry the money", the other is
     * free.
     *
     * Everything non-null is compared as an integer. The two sides come from
     * different places: Stripe's SDK hands back an int, while this side may hold
     * a numeric string depending on the driver and the cast. `!==` between
     * `"1999"` and `1999` is a difference, and a false difference here archives
     * a live price and creates a replacement, churning the price id and
     * orphaning whatever referenced it — silently. That is the same failure the
     * `sameShape` comparison below exists to prevent, on a scalar.
     */
    protected static function sameAmount(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }

        return (int) $a === (int) $b;
    }

    /** Deep equality that ignores map key order. Lists keep theirs. */
    protected static function sameShape(mixed $a, mixed $b): bool
    {
        $a = is_object($a) ? json_decode((string) json_encode($a), true) : $a;
        $b = is_object($b) ? json_decode((string) json_encode($b), true) : $b;

        if (! is_array($a) || ! is_array($b)) {
            return $a === $b;
        }

        if (array_is_list($a) || array_is_list($b)) {
            if (! array_is_list($a) || ! array_is_list($b) || count($a) !== count($b)) {
                return false;
            }

            foreach ($a as $i => $item) {
                if (! self::sameShape($item, $b[$i])) {
                    return false;
                }
            }

            return true;
        }

        ksort($a);
        ksort($b);

        if (array_keys($a) !== array_keys($b)) {
            return false;
        }

        foreach ($a as $k => $v) {
            if (! self::sameShape($v, $b[$k])) {
                return false;
            }
        }

        return true;
    }

}

