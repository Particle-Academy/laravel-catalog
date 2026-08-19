<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LaravelCatalog\Models\Price;

/**
 * The schema change, against a table with rows in it.
 *
 * `unit_amount` was `unsignedInteger` NOT NULL, so a tiered price could not be
 * stored at all and any amount above about $42.9M overflowed. Both changes are
 * strict widenings, which is what makes the migration safe to run against live
 * billing data — but the rollback is not symmetric, and the test that matters
 * most is the one asserting it REFUSES rather than truncating.
 */
it('accepts a price with no unit amount', function () {
    $this->migrateCatalog();

    $productId = (string) Str::ulid();
    DB::table('products')->insert([
        'id' => $productId,
        'name' => 'Metered',
        'active' => true,
        'order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $price = Price::create([
        'product_id' => $productId,
        'currency' => 'USD',
        'unit_amount' => null,
        'type' => Price::TYPE_RECURRING,
        'recurring_interval' => 'month',
        'billing_scheme' => 'tiered',
        'tiers_mode' => 'graduated',
        'tiers' => [
            ['up_to' => 1000, 'unit_amount' => 0],
            ['up_to' => 'inf', 'unit_amount' => 1],
        ],
    ]);

    expect($price->fresh()->unit_amount)->toBeNull();
    expect($price->fresh()->amountCents())->toBeNull();
});

it('stores an amount past the old unsigned-integer ceiling', function () {
    $this->migrateCatalog();

    $productId = (string) Str::ulid();
    DB::table('products')->insert([
        'id' => $productId,
        'name' => 'Enterprise',
        'active' => true,
        'order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 5_000_000_000 minor units — JPY 5bn, roughly $33M, and past the
    // 4,294,967,295 an unsignedInteger can hold.
    $price = Price::create([
        'product_id' => $productId,
        'currency' => 'JPY',
        'unit_amount' => 5_000_000_000,
        'type' => Price::TYPE_ONE_TIME,
    ]);

    expect($price->fresh()->unit_amount)->toBe(5_000_000_000);

    // SQLite is dynamically typed and stores this whatever the declaration
    // says, so the round trip above passes on the old schema too. The width is
    // only enforced by MySQL and Postgres, which the suite does not run — so the
    // assertion that actually carries it is the DECLARATION.
    foreach ([
        'database/migrations/2024_01_01_000002_create_prices_table.php',
        'database/migrations/2026_08_19_000001_make_price_unit_amount_nullable.php',
    ] as $migration) {
        expect(file_get_contents(__DIR__.'/../../'.$migration))
            ->toContain("unsignedBigInteger('unit_amount')");
    }
});

it('refuses to roll back over a price that has no unit amount', function () {
    $this->migrateCatalog();

    $productId = (string) Str::ulid();
    DB::table('products')->insert([
        'id' => $productId,
        'name' => 'Metered',
        'active' => true,
        'order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('prices')->insert([
        'id' => (string) Str::ulid(),
        'product_id' => $productId,
        'currency' => 'USD',
        'unit_amount' => null,
        'type' => 'recurring',
        'active' => true,
        'order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require __DIR__.'/../../database/migrations/2026_08_19_000001_make_price_unit_amount_nullable.php';

    // Writing 0 over a tiered price's null would turn it into a free one. The
    // rollback refuses and says which rows, rather than choosing.
    $migration->down();
})->throws(RuntimeException::class, 'tiered or custom-amount price');

it('reports the column as nullable after migrating', function () {
    $this->migrateCatalog();

    $column = collect(Schema::getColumns('prices'))->firstWhere('name', 'unit_amount');

    expect($column['nullable'])->toBeTrue();
});
