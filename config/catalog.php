<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Sync to Stripe
    |--------------------------------------------------------------------------
    |
    | When enabled, products and prices will automatically sync to Stripe
    | when created or updated. Set to false to manually control syncing.
    |
    */

    'auto_sync_stripe' => env('CATALOG_AUTO_SYNC_STRIPE', false),

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    |
    | The queue connection to use for syncing products to Stripe.
    |
    */

    'queue_connection' => env('CATALOG_QUEUE_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Channel
    |--------------------------------------------------------------------------
    |
    | The broadcasting channel name for product sync events.
    |
    */

    'broadcast_channel' => 'admin.products',
];
