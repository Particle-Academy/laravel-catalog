<?php

namespace LaravelCatalog\Database\Factories;

use LaravelCatalog\Models\ProductFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\LaravelCatalog\Models\ProductFeature>
 */
class ProductFeatureFactory extends Factory
{
    protected $model = ProductFeature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->sentence(8),
            'type' => $this->faker->randomElement(['boolean', 'resource']),
            'config' => [],
        ];
    }
}

