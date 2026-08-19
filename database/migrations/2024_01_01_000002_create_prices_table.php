<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $pricesTable = config('catalog.tables.prices') ?? 'prices';
        $productsTable = config('catalog.tables.products') ?? 'products';

        if (Schema::hasTable($pricesTable)) {
            return; // already present (or a renamed/forked install)
        }
        if (! Schema::hasTable($productsTable)) {
            return; // FK target absent — defer to the consumer's own migration
        }

        Schema::create($pricesTable, function (Blueprint $table) use ($productsTable) {
            $table->ulid('id')->primary();

            // Foreign key to products
            $table->foreignUlid('product_id')
                ->constrained($productsTable)
                ->cascadeOnDelete();

            // Stripe Price attributes
            $table->boolean('active')->default(true);
            $table->string('currency', 3)->default('USD');
            // Nullable, because Stripe sets NO unit amount on a `tiered` or
            // `custom_unit_amount` price -- the tiers carry the money. Bigint
            // because unsignedInteger caps at about $42.9M, and a great deal
            // less in a zero-decimal currency. Both are strict widenings; see
            // 2026_08_19_000001_make_price_unit_amount_nullable.
            $table->unsignedBigInteger('unit_amount')->nullable(); // minor units

            // Recurring subscription fields (nullable for one-time prices)
            $table->string('recurring_interval')->nullable(); // month, year
            $table->unsignedTinyInteger('recurring_interval_count')->nullable()->default(1);
            $table->unsignedInteger('recurring_trial_period_days')->nullable();

            // Price type: recurring or one_time
            $table->string('type')->default('recurring'); // recurring, one_time

            // Metadata for feature allowances (seats, tokens, MCP calls)
            $table->json('metadata')->nullable();

            // External mapping (Stripe price ID)
            $table->string('external_id')->nullable()->index();

            // Display ordering
            $table->integer('order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'active']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('catalog.tables.prices') ?? 'prices');
    }
};
