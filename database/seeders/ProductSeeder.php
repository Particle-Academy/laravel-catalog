<?php

namespace LaravelCatalog\Database\Seeders;

use LaravelCatalog\Models\Price;
use LaravelCatalog\Models\Product;
use LaravelCatalog\Services\StripeCatalogService;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalogService = app(StripeCatalogService::class);
        $syncToStripe = config('billing.auto_sync_stripe', false);

        $this->command->info('💰 Seeding products and pricing plans...');

        $products = [
            [
                'name' => 'Starter',
                'description' => 'Good for early projects and small teams.',
                'order' => 10,
                'metadata' => [
                    'storefront' => [
                        'plan' => [
                            'show' => true,
                            'recommended' => false,
                        ],
                    ],
                ],
                'prices' => [
                    [
                        'unit_amount' => 0,
                        'currency' => 'USD',
                        'recurring_interval' => 'month',
                        'recurring_interval_count' => 1,
                        'recurring_trial_period_days' => 14,
                        'type' => Price::TYPE_RECURRING,
                        'metadata' => [
                            'seats_included' => 3,
                            'tokens_per_period' => 10000,
                            'mcp_calls_per_period' => 1000,
                        ],
                        'order' => 0,
                    ],
                    [
                        'unit_amount' => 0, // Free plan, yearly is also free
                        'currency' => 'USD',
                        'recurring_interval' => 'year',
                        'recurring_interval_count' => 1,
                        'recurring_trial_period_days' => 14,
                        'type' => Price::TYPE_RECURRING,
                        'metadata' => [
                            'seats_included' => 3,
                            'tokens_per_period' => 120000, // 12x monthly
                            'mcp_calls_per_period' => 12000,
                        ],
                        'order' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Pro',
                'description' => 'For growing teams needing more projects and members.',
                'order' => 20,
                'metadata' => [
                    'storefront' => [
                        'plan' => [
                            'show' => true,
                            'recommended' => true, // Mark Pro as recommended
                        ],
                    ],
                ],
                'prices' => [
                    [
                        'unit_amount' => 19900, // $199.00
                        'currency' => 'USD',
                        'recurring_interval' => 'month',
                        'recurring_interval_count' => 1,
                        'recurring_trial_period_days' => 14,
                        'type' => Price::TYPE_RECURRING,
                        'metadata' => [
                            'seats_included' => 15,
                            'tokens_per_period' => 100000,
                            'mcp_calls_per_period' => 10000,
                        ],
                        'order' => 0,
                    ],
                    [
                        'unit_amount' => 199000, // $1990.00 yearly (equivalent to ~$165.83/month, ~17% savings)
                        'currency' => 'USD',
                        'recurring_interval' => 'year',
                        'recurring_interval_count' => 1,
                        'recurring_trial_period_days' => 14,
                        'type' => Price::TYPE_RECURRING,
                        'metadata' => [
                            'seats_included' => 15,
                            'tokens_per_period' => 1200000, // 12x monthly
                            'mcp_calls_per_period' => 120000,
                        ],
                        'order' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Business',
                'description' => 'For organizations coordinating multiple teams.',
                'order' => 30,
                'metadata' => [
                    'storefront' => [
                        'plan' => [
                            'show' => true,
                            'recommended' => false,
                        ],
                    ],
                ],
                'prices' => [
                    [
                        'unit_amount' => 49900, // $499.00
                        'currency' => 'USD',
                        'recurring_interval' => 'month',
                        'recurring_interval_count' => 1,
                        'recurring_trial_period_days' => 14,
                        'type' => Price::TYPE_RECURRING,
                        'metadata' => [
                            'seats_included' => null, // Unlimited
                            'tokens_per_period' => 1000000,
                            'mcp_calls_per_period' => 100000,
                        ],
                        'order' => 0,
                    ],
                    [
                        'unit_amount' => 478800, // $4788.00 yearly (equivalent to ~$399/month, ~20% savings)
                        'currency' => 'USD',
                        'recurring_interval' => 'year',
                        'recurring_interval_count' => 1,
                        'recurring_trial_period_days' => 14,
                        'type' => Price::TYPE_RECURRING,
                        'metadata' => [
                            'seats_included' => null, // Unlimited
                            'tokens_per_period' => 12000000, // 12x monthly
                            'mcp_calls_per_period' => 1200000,
                        ],
                        'order' => 1,
                    ],
                ],
            ],
        ];

        $productCount = 0;
        $priceCount = 0;

        foreach ($products as $productData) {
            $prices = $productData['prices'];
            unset($productData['prices']);

            // Preserve existing metadata if provided, otherwise use empty array
            $metadata = $productData['metadata'] ?? [];

            $product = Product::updateOrCreate(
                ['name' => $productData['name']],
                array_merge($productData, [
                    'active' => true,
                    'images' => [],
                    'metadata' => $metadata,
                ])
            );

            $productCount++;
            $wasCreated = $product->wasRecentlyCreated;

            // Sync product to Stripe if enabled
            if ($syncToStripe) {
                try {
                    $catalogService->syncProduct($product);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to sync product to Stripe during seeding', [
                        'product' => $product->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create prices for this product
            foreach ($prices as $priceData) {
                $price = Price::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'unit_amount' => $priceData['unit_amount'],
                        'currency' => $priceData['currency'],
                        'recurring_interval' => $priceData['recurring_interval'] ?? null,
                    ],
                    array_merge($priceData, [
                        'active' => true,
                    ])
                );

                $priceCount++;

                // Sync price to Stripe if enabled
                if ($syncToStripe) {
                    try {
                        $catalogService->syncPrice($price);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning('Failed to sync price to Stripe during seeding', [
                            'price' => $price->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $this->command->line('  ✓ Created/updated '.$productCount.' products with '.$priceCount.' pricing plans');
    }
}
