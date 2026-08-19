<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the eight columns the models have always referenced and no migration ever
 * created.
 *
 * ## The same bug as `lookup_key`, seven more times
 *
 * `lookup_key` was fillable, cast, and read during Stripe sync with no column
 * behind it — reported as #4, fixed in 0.11.0. The identical shape survived
 * beside it for `pricing_model`, `billing_scheme`, `tiers`, `tiers_mode`,
 * `transform_quantity`, `custom_unit_amount`, `nickname` and `last_synced_at`
 * on `prices`, plus `last_synced_at` on `products`.
 *
 * All eight are in `$fillable`, six are in `casts()`, and
 * `StripeCatalogService::syncPrice()` branches on three of them. With no column
 * there, writing throws and reading returns null forever — so
 * `$price->billing_scheme` was always null, the `'tiered'` branch never fired,
 * and **the whole advanced-pricing feature set was dead code** in a package that
 * documents it.
 *
 * This is the reason making `unit_amount` nullable (the migration before this
 * one) was necessary but not sufficient: a tiered price needs somewhere to put
 * its tiers as well as permission to have no unit amount.
 *
 * ## Safe on live data
 *
 * Every column is nullable with no default and nothing backfills. An existing
 * per-unit price reads `billing_scheme = null`, which is the same `null` its
 * model attribute returned before this ran — so behaviour is identical until
 * somebody sets one. Fully reversible.
 *
 * ## Table names come from config
 *
 * Like every migration here. Naming them literally is a bug in two directions
 * for a prefixed install: the ALTER misses the real `catalog_prices`, and if the
 * app owns an unrelated `prices` table of its own — the exact reason to prefix —
 * the columns land on THAT one. Reported in #7.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prices = config('catalog.tables.prices') ?? 'prices';
        $products = config('catalog.tables.products') ?? 'products';

        if (Schema::hasTable($prices)) {
            Schema::table($prices, function (Blueprint $table) use ($prices) {
                $this->addMissing($table, $prices, [
                    // "per_unit" | "tiered". The branch in syncPrice() that
                    // never fired.
                    'billing_scheme' => fn (Blueprint $t) => $t->string('billing_scheme')->nullable(),
                    // "graduated" | "volume".
                    'tiers_mode' => fn (Blueprint $t) => $t->string('tiers_mode')->nullable(),
                    // The list Stripe prices from. Order is part of the meaning,
                    // which is why the sync compares it order-sensitively.
                    'tiers' => fn (Blueprint $t) => $t->json('tiers')->nullable(),
                    // { divide_by, round }.
                    'transform_quantity' => fn (Blueprint $t) => $t->json('transform_quantity')->nullable(),
                    // { enabled, minimum, maximum, preset } — pay-what-you-want.
                    'custom_unit_amount' => fn (Blueprint $t) => $t->json('custom_unit_amount')->nullable(),
                    // The package's own pricing taxonomy (Price::PRICING_MODEL_*),
                    // which decides Stripe's metered/licensed usage_type.
                    'pricing_model' => fn (Blueprint $t) => $t->string('pricing_model')->nullable(),
                    // Stripe's internal label for the price.
                    'nickname' => fn (Blueprint $t) => $t->string('nickname')->nullable(),
                    'last_synced_at' => fn (Blueprint $t) => $t->timestamp('last_synced_at')->nullable(),
                ]);
            });
        }

        if (Schema::hasTable($products)) {
            Schema::table($products, function (Blueprint $table) use ($products) {
                $this->addMissing($table, $products, [
                    'last_synced_at' => fn (Blueprint $t) => $t->timestamp('last_synced_at')->nullable(),
                ]);
            });
        }
    }

    public function down(): void
    {
        $prices = config('catalog.tables.prices') ?? 'prices';
        $products = config('catalog.tables.products') ?? 'products';

        $this->dropIfPresent($prices, [
            'billing_scheme', 'tiers_mode', 'tiers', 'transform_quantity',
            'custom_unit_amount', 'pricing_model', 'nickname', 'last_synced_at',
        ]);

        $this->dropIfPresent($products, ['last_synced_at']);
    }

    /**
     * Add only the columns that are not already there.
     *
     * An install may have added some by hand precisely because they were
     * missing, and a blind `ALTER` would fail the whole migration on the first
     * one. The guard also makes a re-run a no-op.
     *
     * @param  array<string, callable(Blueprint): mixed>  $columns
     */
    private function addMissing(Blueprint $table, string $tableName, array $columns): void
    {
        foreach ($columns as $name => $define) {
            if (! Schema::hasColumn($tableName, $name)) {
                $define($table);
            }
        }
    }

    /** @param  list<string>  $columns */
    private function dropIfPresent(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $present = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($tableName, $column),
        ));

        if ($present === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });
    }
};
