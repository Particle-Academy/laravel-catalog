<?php

namespace LaravelCatalog\Database\Factories;

use LaravelCatalog\Models\Price;
use LaravelCatalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    protected $model = Price::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'active' => true,
            'currency' => 'USD',
            'unit_amount' => fake()->numberBetween(1000, 50000), // $10 to $500 in cents
            'recurring_interval' => 'month',
            'recurring_interval_count' => 1,
            'recurring_trial_period_days' => null,
            'type' => Price::TYPE_RECURRING,
            'metadata' => [],
            'external_id' => null,
            'order' => 0,
        ];
    }

    /**
     * Create a one-time price.
     */
    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Price::TYPE_ONE_TIME,
            'recurring_interval' => null,
            'recurring_interval_count' => null,
            'recurring_trial_period_days' => null,
        ]);
    }

    /**
     * Create a yearly recurring price.
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'recurring_interval' => 'year',
            'recurring_interval_count' => 1,
        ]);
    }
}

