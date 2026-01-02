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
        Schema::create('product_feature_configs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignUlid('product_feature_id')
                ->constrained('product_features')
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
        Schema::dropIfExists('product_feature_configs');
    }
};
