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
        $configsTable = config('catalog.tables.product_feature_configs') ?? 'product_feature_configs';
        $productsTable = config('catalog.tables.products') ?? 'products';
        $productFeaturesTable = config('catalog.tables.product_features') ?? 'product_features';

        if (Schema::hasTable($configsTable)) {
            return; // already present (or a renamed/forked install)
        }
        if (! Schema::hasTable($productsTable) || ! Schema::hasTable($productFeaturesTable)) {
            return; // FK target(s) absent — defer to the consumer's own migration
        }

        Schema::create($configsTable, function (Blueprint $table) use ($productsTable, $productFeaturesTable) {
            $table->ulid('id')->primary();

            $table->foreignUlid('product_id')
                ->constrained($productsTable)
                ->cascadeOnDelete();

            $table->foreignUlid('product_feature_id')
                ->constrained($productFeaturesTable)
                ->cascadeOnDelete();

            // Boolean toggle for simple on/off features
            $table->boolean('enabled')->default(false);

            // Included quantity for resource-based features (seats, tokens, etc.)
            $table->unsignedBigInteger('included_quantity')->nullable();

            // Optional overage limit for soft caps or add-on handling
            $table->unsignedBigInteger('overage_limit')->nullable();

            // Arbitrary JSON config, e.g. warning thresholds or UI hints
            $table->json('config')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'product_feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('catalog.tables.product_feature_configs') ?? 'product_feature_configs');
    }
};

