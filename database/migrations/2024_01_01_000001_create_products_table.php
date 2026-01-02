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
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Stripe Product attributes
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->json('images')->nullable(); // Array of image URLs
            $table->json('metadata')->nullable();
            $table->string('statement_descriptor')->nullable();
            $table->string('unit_label')->nullable();

            // External mapping (Stripe product ID)
            $table->string('external_id')->nullable()->index();

            // Display ordering
            $table->integer('order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
