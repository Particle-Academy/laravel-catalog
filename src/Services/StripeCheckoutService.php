<?php

namespace LaravelCatalog\Services;

use LaravelCatalog\Models\Price;
use Laravel\Cashier\Checkout;
use Stripe\Checkout\Session;

/**
 * StripeCheckoutService
 * Created to generate Stripe Checkout sessions for subscriptions and one-off payments,
 * using our configured Products/Prices and their external Stripe price IDs.
 */
class StripeCheckoutService
{
    /**
     * Create a Stripe Checkout session for a recurring subscription.
     * Why: Enables users to subscribe via Stripe-hosted checkout, linking to our Price's Stripe price ID.
     *
     * @param  mixed  $owner  The billable entity (User or Organization) - must implement Laravel Cashier's Billable contract
     * @param  Price  $price  The recurring price to subscribe to
     * @param  string  $successUrl  URL to redirect to on successful checkout
     * @param  string  $cancelUrl  URL to redirect to if checkout is cancelled
     * @param  array  $metadata  Additional metadata to attach to the subscription
     */
    public function subscriptionCheckout(
        $owner,
        Price $price,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): Checkout {
        $stripePriceId = $price->stripePriceId();

        if (! $stripePriceId) {
            throw new \InvalidArgumentException('Price does not have a Stripe price ID. Sync the price to Stripe first.');
        }

        if (! $price->isRecurring()) {
            throw new \InvalidArgumentException('Cannot create subscription checkout for a one-time price.');
        }

        $sessionOptions = [
            'mode' => Session::MODE_SUBSCRIPTION,
            'line_items' => [
                [
                    'price' => $stripePriceId,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'subscription_data' => [
                'metadata' => array_merge([
                    'price_id' => (string) $price->id,
                    'product_id' => (string) $price->product_id,
                ], $metadata),
            ],
        ];

        // Add trial period if the price has one configured
        if ($price->trialDays()) {
            $sessionOptions['subscription_data']['trial_period_days'] = $price->trialDays();
        }

        return Checkout::create($owner, $sessionOptions);
    }

    /**
     * Create a Stripe Checkout session for a one-time payment (add-on purchase).
     * Why: Enables users to purchase add-ons via Stripe-hosted checkout.
     *
     * @param  mixed  $owner  The billable entity (User or Organization) - must implement Laravel Cashier's Billable contract
     * @param  Price  $price  The one-time price to purchase
     * @param  int  $quantity  Number of units to purchase
     * @param  string  $successUrl  URL to redirect to on successful checkout
     * @param  string  $cancelUrl  URL to redirect to if checkout is cancelled
     * @param  array  $metadata  Additional metadata to attach to the payment
     */
    public function oneTimeCheckout(
        $owner,
        Price $price,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): Checkout {
        $stripePriceId = $price->stripePriceId();

        if (! $stripePriceId) {
            throw new \InvalidArgumentException('Price does not have a Stripe price ID. Sync the price to Stripe first.');
        }

        if (! $price->isOneTime()) {
            throw new \InvalidArgumentException('Cannot create one-time checkout for a recurring price.');
        }

        $sessionOptions = [
            'mode' => Session::MODE_PAYMENT,
            'line_items' => [
                [
                    'price' => $stripePriceId,
                    'quantity' => $quantity,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'payment_intent_data' => [
                'metadata' => array_merge([
                    'price_id' => (string) $price->id,
                    'product_id' => (string) $price->product_id,
                ], $metadata),
            ],
            'invoice_creation' => [
                'enabled' => true,
                'invoice_data' => [
                    'metadata' => [
                        'price_id' => (string) $price->id,
                        'product_id' => (string) $price->product_id,
                    ],
                ],
            ],
        ];

        return Checkout::create($owner, $sessionOptions);
    }

    /**
     * Get the Stripe Checkout session URL for a subscription.
     * Why: Convenience method to directly get the redirect URL.
     */
    public function getSubscriptionCheckoutUrl(
        $owner,
        Price $price,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): string {
        $checkout = $this->subscriptionCheckout($owner, $price, $successUrl, $cancelUrl, $metadata);

        return $checkout->asStripeCheckoutSession()->url;
    }

    /**
     * Get the Stripe Checkout session URL for a one-time payment.
     * Why: Convenience method to directly get the redirect URL.
     */
    public function getOneTimeCheckoutUrl(
        $owner,
        Price $price,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): string {
        $checkout = $this->oneTimeCheckout($owner, $price, $quantity, $successUrl, $cancelUrl, $metadata);

        return $checkout->asStripeCheckoutSession()->url;
    }
}

