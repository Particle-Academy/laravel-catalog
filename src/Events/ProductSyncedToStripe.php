<?php

namespace LaravelCatalog\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ProductSyncedToStripe
 * Created to broadcast when a product and its prices have finished syncing to Stripe.
 * Allows Livewire components to refresh UI (sync status) in real time.
 */
class ProductSyncedToStripe implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $productId;

    /**
     * Create a new event instance.
     */
    public function __construct(string $productId)
    {
        $this->productId = $productId;
    }

    /**
     * The channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('admin.products');
    }

    /**
     * The event name used on the client.
     */
    public function broadcastAs(): string
    {
        return 'product.synced';
    }
}

