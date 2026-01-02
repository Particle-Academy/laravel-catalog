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
        $this->stripe = new StripeClient(config('cashier.secret'));
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
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
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
                'unit_amount' => $price->unit_amount,
                'active' => $price->active,
                'metadata' => array_merge($price->metadata() ?? [], [
                    // Shared internal ID allowing us to link archived and replacement Stripe Prices.
                    'price_id' => $price->sharedPriceId(),
                    'product_id' => $price->product_id,
                    'lookup_key' => $price->lookup_key,
                ]),
            ];

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
                    $pricingChanged = $existingPrice->unit_amount !== $price->unit_amount
                        || $existingPrice->currency !== strtolower($price->currency)
                        || ($price->type === Price::TYPE_RECURRING && (
                            $existingPrice->recurring->interval !== $price->recurring_interval
                            || $existingPrice->recurring->interval_count !== ($price->recurring_interval_count ?? 1)
                            || ($existingPrice->recurring->usage_type ?? 'licensed') !== ($stripePriceData['recurring']['usage_type'] ?? 'licensed')
                        ))
                        || ($existingPrice->billing_scheme ?? 'per_unit') !== ($stripePriceData['billing_scheme'] ?? 'per_unit')
                        || ($existingPrice->tiers_mode ?? null) !== ($stripePriceData['tiers_mode'] ?? null)
                        || json_encode($existingPrice->tiers ?? []) !== json_encode($stripePriceData['tiers'] ?? [])
                        || json_encode($existingPrice->transform_quantity ?? []) !== json_encode($stripePriceData['transform_quantity'] ?? [])
                        || json_encode($existingPrice->custom_unit_amount ?? []) !== json_encode($stripePriceData['custom_unit_amount'] ?? []);

                    if ($pricingChanged) {
                        // Archive old price
                        $this->stripe->prices->update($price->external_id, ['active' => false]);

                        // Create new price (prices are immutable)
                        $stripePrice = $this->stripe->prices->create($stripePriceData);

                        $price->external_id = $stripePrice->id;
                        $price->save();
                    } else {
                        // Just update metadata/active status
                        $this->stripe->prices->update($price->external_id, [
                            'active' => $price->active,
                            'metadata' => $stripePriceData['metadata'],
                        ]);
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
}

