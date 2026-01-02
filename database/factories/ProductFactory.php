<?php

namespace LaravelCatalog\Database\Factories;

use LaravelCatalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'active' => true,
            'images' => [],
            'metadata' => [],
            'statement_descriptor' => null,
            'unit_label' => null,
            'external_id' => null,
            'order' => 0,
        ];
    }
}
