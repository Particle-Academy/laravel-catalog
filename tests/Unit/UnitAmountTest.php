<?php

declare(strict_types=1);

use LaravelCatalog\Services\StripeCatalogService;

/**
 * A price may have NO unit amount, and null is not zero.
 *
 * Stripe sets no `unit_amount` on a `tiered` or `custom_unit_amount` price — the
 * tiers carry the money. This package models `tiers`, `tiers_mode` and
 * `custom_unit_amount`, sends them, and compares them on the way back, and then
 * made every one of them unrepresentable one column away: `unsignedInteger` NOT
 * NULL.
 *
 * The comparison matters as much as the column. Prices are immutable, so
 * "changed" archives the live price and creates a replacement — a churned id and
 * orphaned references, silently. `null` vs `0` and `"1999"` vs `1999` are both
 * ways to get that wrong.
 */
final class AmountProbe extends StripeCatalogService
{
    public function __construct()
    {
        // Deliberately does NOT call parent::__construct(): that builds a
        // StripeClient from config, and this probe is about pure comparison.
        // No network, ever.
    }

    public static function probe(mixed $a, mixed $b): bool
    {
        return self::sameAmount($a, $b);
    }
}

it('treats two equal amounts as unchanged', function () {
    expect(AmountProbe::probe(1999, 1999))->toBeTrue();
});

it('sees a real price change', function () {
    expect(AmountProbe::probe(1999, 2499))->toBeFalse();
});

it('does not confuse "no unit amount" with "free"', function () {
    // A tiered price has null; a free price has 0. Treating them as equal would
    // leave a tiered price un-updated, or archive a free one for nothing.
    expect(AmountProbe::probe(null, 0))->toBeFalse();
    expect(AmountProbe::probe(0, null))->toBeFalse();
});

it('treats two tiered prices as unchanged', function () {
    expect(AmountProbe::probe(null, null))->toBeTrue();
});

it('does not read a numeric string as a change', function () {
    // Stripe's SDK hands back an int; this side may hold a numeric string
    // depending on the driver. A strict !== between them archives a live price
    // and churns its id for nothing.
    expect(AmountProbe::probe(1999, '1999'))->toBeTrue();
});
