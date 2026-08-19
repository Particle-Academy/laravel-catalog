<?php

declare(strict_types=1);

use LaravelCatalog\Services\StripeCatalogService;

/**
 * Key order is not a pricing change.
 *
 * `transform_quantity` and `custom_unit_amount` were compared with
 * `json_encode`. Both are maps, and the two sides come from different places —
 * Stripe returns its own key order, this package builds its own — so identical
 * pricing compared unequal. Prices are immutable, so "changed" archives the live
 * price and creates a replacement: a churned id, orphaned references, silently.
 */
final class SameShapeProbe extends StripeCatalogService
{
    public static function probe(mixed $a, mixed $b): bool
    {
        return self::sameShape($a, $b);
    }
}

it('sees past map key order', function () {
    expect(SameShapeProbe::probe(
        ['divide_by' => 10, 'round' => 'up'],
        ['round' => 'up', 'divide_by' => 10],
    ))->toBeTrue();
});

it('still notices a real difference', function () {
    expect(SameShapeProbe::probe(['divide_by' => 10], ['divide_by' => 20]))->toBeFalse();
    expect(SameShapeProbe::probe(['divide_by' => 10], ['divide_by' => 10, 'round' => 'up']))->toBeFalse();
});

it('keeps list order significant', function () {
    expect(SameShapeProbe::probe([1, 2], [2, 1]))->toBeFalse();
});
