<?php

namespace LaravelCatalog\Jobs;

use LaravelCatalog\Models\Product;
use LaravelCatalog\Services\StripeCatalogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * SyncProductToStripe
 * Created to sync a Product and all of its Prices to Stripe on a queue.
 * This keeps UI interactions fast and allows us to track last_synced_at timestamps.
 */
class SyncProductToStripe implements ShouldQueue
{
    use Queueable;

    /**
     * The product ULID to sync.
     */
    public string $productId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $productId)
    {
        $this->productId = $productId;
    }

    /**
     * Execute the job.
     */
    public function handle(StripeCatalogService $catalogService): void
    {
        $product = Product::query()
            ->with(['prices' => function ($query): void {
                $query->withoutTrashed();
            }])
            ->find($this->productId);

        if (! $product) {
            return;
        }

        $catalogService->syncProductAndPrices($product);

        $now = Carbon::now();

        $product->last_synced_at = $now;
        $product->save();

        foreach ($product->prices as $price) {
            $price->last_synced_at = $now;
            $price->save();
        }

        event(new \LaravelCatalog\Events\ProductSyncedToStripe($product->id));
    }
}

