<?php

namespace LaravelCatalog;

use LaravelCatalog\Models\Price;
use LaravelCatalog\Models\Product;
use LaravelCatalog\Services\StripeCatalogService;
use LaravelCatalog\Services\StripeCheckoutService;
use Laravel\Cashier\Checkout;

/**
 * CatalogManager
 * Created to provide a unified interface for all catalog functionality.
 * This allows the package to be used without UI dependencies via the Catalog facade.
 */
class CatalogManager
{
    public function __construct(
        protected StripeCatalogService $catalogService,
        protected StripeCheckoutService $checkoutService
    ) {
    }

    /**
     * Get the StripeCatalogService instance.
     */
    public function catalogService(): StripeCatalogService
    {
        return $this->catalogService;
    }

    /**
     * Get the StripeCheckoutService instance.
     */
    public function checkoutService(): StripeCheckoutService
    {
        return $this->checkoutService;
    }

    /**
     * Sync a Product to Stripe.
     */
    public function syncProduct(Product $product): Product
    {
        return $this->catalogService->syncProduct($product);
    }

    /**
     * Sync a Price to Stripe.
     */
    public function syncPrice(Price $price): Price
    {
        return $this->catalogService->syncPrice($price);
    }

    /**
     * Sync a Product and all its Prices to Stripe.
     */
    public function syncProductAndPrices(Product $product): Product
    {
        return $this->catalogService->syncProductAndPrices($product);
    }

    /**
     * Test Stripe connection.
     */
    public function testConnection(): array
    {
        return $this->catalogService->testConnection();
    }

    /**
     * Create a Stripe Checkout session for a recurring subscription.
     */
    public function subscriptionCheckout(
        $owner,
        Price $price,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): Checkout {
        return $this->checkoutService->subscriptionCheckout($owner, $price, $successUrl, $cancelUrl, $metadata);
    }

    /**
     * Create a Stripe Checkout session for a one-time payment.
     */
    public function oneTimeCheckout(
        $owner,
        Price $price,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): Checkout {
        return $this->checkoutService->oneTimeCheckout($owner, $price, $quantity, $successUrl, $cancelUrl, $metadata);
    }

    /**
     * Get the Stripe Checkout session URL for a subscription.
     */
    public function getSubscriptionCheckoutUrl(
        $owner,
        Price $price,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): string {
        return $this->checkoutService->getSubscriptionCheckoutUrl($owner, $price, $successUrl, $cancelUrl, $metadata);
    }

    /**
     * Get the Stripe Checkout session URL for a one-time payment.
     */
    public function getOneTimeCheckoutUrl(
        $owner,
        Price $price,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): string {
        return $this->checkoutService->getOneTimeCheckoutUrl($owner, $price, $quantity, $successUrl, $cancelUrl, $metadata);
    }
}

