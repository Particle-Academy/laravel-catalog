<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `prices.unit_amount` becomes a NULLABLE UNSIGNED BIGINT.
 *
 * ## Why nullable
 *
 * Stripe sets **no unit amount at all** on a `tiered` price or a
 * `custom_unit_amount` price — the tiers carry the money. This package models
 * `tiers`, `tiers_mode` and `custom_unit_amount`, sends them to Stripe, and
 * compares them on the way back, and then made every one of them
 * unrepresentable one column away: `unsignedInteger` NOT NULL.
 *
 * A tiered price therefore had to be stored with a fabricated `unit_amount`,
 * which is then sent to Stripe alongside `tiers` — an API error at best, and a
 * per-unit charge instead of a tiered one if it is not.
 *
 * ## Why bigint
 *
 * `unsignedInteger` caps at 4,294,967,295 minor units — about **$42.9M**, and
 * a great deal less than that in a zero-decimal currency: JPY 4.29bn is roughly
 * $28M, IDR 4.29bn about $270k. That is a real ceiling for an annual enterprise
 * contract, and four bytes a row is not a reason to keep it.
 *
 * It stays UNSIGNED. Stripe rejects a negative `unit_amount`; a credit is a
 * coupon or a credit note, not a negative price. Signing the column to model
 * something the API does not have would invite exactly one bug.
 *
 * Both changes are strict WIDENINGS, so no existing row can be affected.
 *
 * ## Rolling back
 *
 * `down()` restores `unsignedInteger NOT NULL` **only when it can do so without
 * losing anything** — no null rows, nothing above the old ceiling. Otherwise it
 * throws and names the count. Silently truncating a price is not an acceptable
 * rollback, and neither is quietly writing 0 over a tiered price's null.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->pricesTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'unit_amount')) {
            // The create migration defers to the consumer's own when its foreign
            // key target is absent, so the table legitimately may not be here.
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('unit_amount')->nullable()->change();
        });
    }

    public function down(): void
    {
        $table = $this->pricesTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'unit_amount')) {
            return;
        }

        $nulls = DB::table($table)->whereNull('unit_amount')->count();

        if ($nulls > 0) {
            throw new RuntimeException(
                "laravel-catalog: cannot restore `{$table}.unit_amount` to NOT NULL — {$nulls} "
                ."price(s) have no unit amount, which is what a tiered or custom-amount price "
                ."looks like.\n\n"
                ."Decide what those prices should be before rolling back. Writing 0 over them "
                ."would turn a tiered price into a free one, which is why this refuses rather "
                ."than choosing:\n"
                ."  SELECT id, lookup_key, billing_scheme FROM {$table} WHERE unit_amount IS NULL;"
            );
        }

        $overflow = DB::table($table)->where('unit_amount', '>', 4294967295)->count();

        if ($overflow > 0) {
            throw new RuntimeException(
                "laravel-catalog: cannot narrow `{$table}.unit_amount` back to an unsigned "
                ."integer — {$overflow} price(s) exceed 4,294,967,295 minor units and would be "
                ."silently truncated. Restore from a backup instead."
            );
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unsignedInteger('unit_amount')->nullable(false)->change();
        });
    }

    private function pricesTable(): string
    {
        return config('catalog.tables.prices') ?? 'prices';
    }
};
