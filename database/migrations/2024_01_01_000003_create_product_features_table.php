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
        Schema::create('product_features', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Feature key used in code (e.g. use-mcp, ai-tokens, seats)
            $table->string('key')->unique();

            // Human readable label and description for admin UX
            $table->string('name');
            $table->text('description')->nullable();

            // Feature type: boolean or resource (seats, tokens, etc.)
            $table->string('type')->default('boolean');

            // Optional JSON configuration for provider-specific or feature-specific metadata
            $table->json('config')->nullable();

            $table->timestamps();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_features');
    }
};
