<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the `lookup_key` column the models have always referenced.
 *
 * `lookup_key` was in `$fillable` on both Product and Price, cast on Product,
 * exposed through a `lookupKey()` accessor, and read during Stripe sync — and
 * no migration ever created it. Writing threw; reading returned null forever,
 * so every synced product wrote `product_lookup_key => null` into Stripe
 * metadata without erroring. Reported by GuardCard.net (#4).
 *
 * ## Why the column rather than removing the field
 *
 * `lookup_key` is not a duplicate of `external_id`, and the two are close to
 * opposites. `external_id` is **Stripe's** opaque id (`prod_…` / `price_…`),
 * assigned by Stripe and saved back after a sync. `lookup_key` is **yours**: a
 * stable, human-readable handle so application code can say `pro-monthly`
 * instead of a ULID or a Stripe id that changes.
 *
 * That distinction is what makes it load-bearing here. Stripe Prices are
 * immutable, so this package archives a price and creates a replacement
 * whenever the amount or interval changes. The replacement gets a NEW Stripe
 * id — and a lookup key is precisely the thing that survives that swap.
 *
 * ## Unique
 *
 * A handle that can point at two rows is not a handle. Stripe enforces the same
 * constraint on its own Price lookup keys, so matching it here means a conflict
 * surfaces at write time rather than as a sync failure later.
 *
 * Nullable, and NULLs do not collide in a unique index on any supported driver,
 * so existing rows are unaffected — nothing to backfill before upgrading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('lookup_key')->nullable()->unique()->after('external_id');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->string('lookup_key')->nullable()->unique()->after('external_id');
        });
    }

    public function down(): void
    {
        // The index goes in its own statement, before the column. SQLite
        // rebuilds the table to drop a column and fails if an index still
        // references it, so doing both in one closure breaks a rollback on the
        // driver most test suites run on.
        foreach (['products', 'prices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique("{$tableName}_lookup_key_unique");
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('lookup_key');
            });
        }
    }
};
