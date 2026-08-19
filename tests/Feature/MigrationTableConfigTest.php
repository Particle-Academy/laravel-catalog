<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The package's migrations must build the tables named in
 * `config('catalog.tables.*')`, not the literal defaults.
 *
 * These tests run the migrations with a NON-DEFAULT prefix configured, which is
 * the only configuration that can catch a hardcoded name — a run on the default
 * names passes whether or not the config is ever read. Regression cover for #7,
 * where `add_lookup_key_to_catalog_tables` named `products` / `prices` outright
 * and broke `migrate:fresh` for every prefixed install.
 */
function useCatalogPrefix(): void
{
    config()->set('catalog.tables', [
        'products' => 'catalog_products',
        'prices' => 'catalog_prices',
        'product_features' => 'catalog_product_features',
        'product_feature_configs' => 'catalog_product_feature_configs',
    ]);
}

/** @return list<string> */
function indexNames(string $table): array
{
    return array_map(
        fn (array $index): string => strtolower((string) $index['name']),
        Schema::getIndexes($table),
    );
}

it('creates every catalog table under the configured names', function () {
    useCatalogPrefix();

    $this->migrateCatalog();

    expect(Schema::hasTable('catalog_products'))->toBeTrue()
        ->and(Schema::hasTable('catalog_prices'))->toBeTrue()
        ->and(Schema::hasTable('catalog_product_features'))->toBeTrue()
        ->and(Schema::hasTable('catalog_product_feature_configs'))->toBeTrue()
        ->and(Schema::hasTable('products'))->toBeFalse()
        ->and(Schema::hasTable('prices'))->toBeFalse();
});

it('adds lookup_key to the configured tables, not the literal defaults', function () {
    useCatalogPrefix();

    $this->migrateCatalog();

    expect(Schema::hasColumn('catalog_products', 'lookup_key'))->toBeTrue()
        ->and(Schema::hasColumn('catalog_prices', 'lookup_key'))->toBeTrue();
});

it('names the lookup_key unique index after the configured table', function () {
    useCatalogPrefix();

    $this->migrateCatalog();

    expect(indexNames('catalog_products'))->toContain('catalog_products_lookup_key_unique')
        ->and(indexNames('catalog_prices'))->toContain('catalog_prices_lookup_key_unique');
});

it('leaves an unrelated application table of the same default name untouched', function () {
    // The whole reason to prefix: the app already owns `products`. A migration
    // that names the table literally silently alters THIS one.
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('sku');
    });

    useCatalogPrefix();

    $this->migrateCatalog();

    expect(Schema::hasColumn('catalog_products', 'lookup_key'))->toBeTrue()
        ->and(Schema::hasColumn('products', 'lookup_key'))->toBeFalse()
        ->and(Schema::hasColumns('products', ['id', 'sku']))->toBeTrue();
});

it('rolls back cleanly when the tables are prefixed', function () {
    useCatalogPrefix();

    $this->migrateCatalog();
    $this->rollbackCatalog();

    expect(Schema::hasTable('catalog_products'))->toBeFalse()
        ->and(Schema::hasTable('catalog_prices'))->toBeFalse();
});

it('rolls the ALTER migrations back off the configured tables, leaving them standing', function () {
    useCatalogPrefix();

    $this->migrateCatalog();

    // Three migrations alter the created tables rather than creating them:
    // lookup_key, the nullable unit_amount, and the advanced-pricing columns.
    // Rolling all three back must leave the four tables in place — a `down()`
    // that names a literal table drops nothing here and the assertions below
    // catch it.
    $this->artisan('migrate:rollback', [
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
        '--step' => 3,
    ])->run();

    expect(Schema::hasTable('catalog_products'))->toBeTrue()
        ->and(Schema::hasColumn('catalog_products', 'lookup_key'))->toBeFalse()
        ->and(Schema::hasColumn('catalog_products', 'last_synced_at'))->toBeFalse()
        ->and(Schema::hasColumn('catalog_prices', 'lookup_key'))->toBeFalse()
        ->and(Schema::hasColumn('catalog_prices', 'billing_scheme'))->toBeFalse()
        ->and(Schema::hasColumn('catalog_prices', 'tiers'))->toBeFalse();
});

it('creates the advanced-pricing columns the models have always referenced', function () {
    useCatalogPrefix();

    $this->migrateCatalog();

    // `billing_scheme`, `tiers`, `tiers_mode`, `transform_quantity`,
    // `custom_unit_amount`, `pricing_model` and `nickname` were fillable, cast
    // and branched on by StripeCatalogService, with no column behind any of
    // them — the same defect as `lookup_key` (#4), seven more times. Writing
    // threw; reading returned null forever, so the `'tiered'` branch could
    // never fire and the whole advanced-pricing feature set was dead.
    foreach ([
        'billing_scheme', 'tiers', 'tiers_mode', 'transform_quantity',
        'custom_unit_amount', 'pricing_model', 'nickname', 'last_synced_at',
    ] as $column) {
        expect(Schema::hasColumn('catalog_prices', $column))
            ->toBeTrue("catalog_prices is missing `{$column}`, which the Price model declares");
    }

    expect(Schema::hasColumn('catalog_products', 'last_synced_at'))->toBeTrue();
});

it('still uses the default table names when nothing is configured', function () {
    $this->migrateCatalog();

    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasColumn('products', 'lookup_key'))->toBeTrue()
        ->and(Schema::hasColumn('prices', 'lookup_key'))->toBeTrue();
});

it('skips a table that does not exist rather than failing the run', function () {
    // The create migrations defer to the consumer when a foreign-key target is
    // missing, so a catalog table can legitimately be absent when this one runs
    // — and `Schema::table()` on a missing table is a hard failure.
    useCatalogPrefix();

    Schema::create('catalog_products', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('external_id')->nullable();
    });

    $this->artisan('migrate', [
        '--path' => realpath(__DIR__.'/../../database/migrations/2026_07_27_000001_add_lookup_key_to_catalog_tables.php'),
        '--realpath' => true,
    ])->run();

    expect(Schema::hasColumn('catalog_products', 'lookup_key'))->toBeTrue()
        ->and(Schema::hasTable('catalog_prices'))->toBeFalse();
});

it('is idempotent when the column is already present', function () {
    useCatalogPrefix();

    $this->migrateCatalog();

    // Applying it a second time is what an install that added the column by
    // hand looks like. Driven directly: Laravel would otherwise see the
    // migration recorded and never enter `up()`.
    $migration = require __DIR__.'/../../database/migrations/2026_07_27_000001_add_lookup_key_to_catalog_tables.php';
    $migration->up();

    expect(Schema::hasColumn('catalog_products', 'lookup_key'))->toBeTrue();
});
