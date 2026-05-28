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

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Catalog's four tables. Override these when your schema differs — e.g.
    | you already have an application `products` table and need to prefix
    | catalog's as `catalog_products`, etc.
    |
    | Both the models (Product / Price / ProductFeature, including the
    | product_feature_configs pivot) and the create migrations read these,
    | so a single config change keeps models, relationships, Stripe sync,
    | and schema in sync without forking the package or hand-editing a
    | published migration.
    |
    | The create migrations also self-skip (no error) when the target table
    | already exists OR when a foreign-key target table is absent at apply
    | time, so they can sit early in your chronological migration order.
    |
    */
    'tables' => [
        'products' => 'products',
        'prices' => 'prices',
        'product_features' => 'product_features',
        'product_feature_configs' => 'product_feature_configs',
    ],
];
