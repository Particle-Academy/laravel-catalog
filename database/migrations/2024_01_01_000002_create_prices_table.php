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
        Schema::create('prices', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Foreign key to products
            $table->foreignUlid('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Stripe Price attributes
            $table->boolean('active')->default(true);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('unit_amount'); // Price in cents

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
        Schema::dropIfExists('prices');
    }
};
