<?php

namespace LaravelCatalog;

/**
 * The catalog package's Live Contract — the PHP half of the declaration in
 * `@particle-academy/fancy-catalog`'s `catalogLive`.
 *
 * It says which broadcast events this package emits and which client query keys
 * each one invalidates. The JS twin declares the identical list, and a test on
 * each side asserts the two match.
 *
 * That parity test is the whole point. A mirror pair drifts when only one side
 * gets edited, and this is the failure mode where drift is invisible: rename an
 * event here and the UI simply stops updating — no exception, no failed
 * request, just a cache nobody invalidated. The test is what turns that into a
 * red build instead of a support ticket.
 *
 * Conventions, matching the JS `LiveContract` type:
 *   - namespace: the bare short name, no `laravel-` prefix.
 *   - event:     `<namespace>.<resource>.<verb>`.
 *   - key:       `[namespace, resource, ...]` — TanStack matches by PREFIX, so
 *                `["catalog"]` invalidates the whole namespace.
 */
final class LiveContract
{
    public const NAMESPACE = 'catalog';

    /** The channel these events broadcast on. */
    public const CHANNEL = 'admin.products';

    /**
     * Event name => the query keys it invalidates.
     *
     * @var array<string, list<list<string>>>
     */
    public const EVENTS = [
        'catalog.product.created' => [['catalog', 'products']],
        'catalog.product.updated' => [['catalog', 'products']],
        'catalog.product.deleted' => [['catalog', 'products']],
        // A price change alters what a product costs, so both caches go stale.
        'catalog.price.created' => [['catalog', 'products'], ['catalog', 'prices']],
        'catalog.price.updated' => [['catalog', 'products'], ['catalog', 'prices']],
        'catalog.price.deleted' => [['catalog', 'products'], ['catalog', 'prices']],
    ];

    /**
     * The contract as the shape the JS side declares, for the parity test.
     *
     * @return array{namespace: string, channel: string, events: list<array{event: string, keys: list<list<string>>}>}
     */
    public static function toArray(): array
    {
        $events = [];

        foreach (self::EVENTS as $event => $keys) {
            $events[] = ['event' => $event, 'keys' => $keys];
        }

        return [
            'namespace' => self::NAMESPACE,
            'channel' => self::CHANNEL,
            'events' => $events,
        ];
    }

    /** Every event name this package promises to broadcast. */
    public static function eventNames(): array
    {
        return array_keys(self::EVENTS);
    }
}
