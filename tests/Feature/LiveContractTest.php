<?php

use LaravelCatalog\LiveContract;

/**
 * The PHP half of the Live Contract parity check.
 *
 * The JS twin asserts the same thing from its side. Both exist because a mirror
 * pair drifts when only ONE side is edited, and neither side can tell on its
 * own: rename an event here and nothing throws — the browser goes on listening
 * for a name nobody broadcasts, the cache is never invalidated, and the UI just
 * stops updating.
 */
it('declares every event under its own namespace', function () {
    foreach (LiveContract::eventNames() as $event) {
        expect($event)->toStartWith(LiveContract::NAMESPACE.'.');
    }
});

it('names every event <namespace>.<resource>.<verb>', function () {
    foreach (LiveContract::eventNames() as $event) {
        expect(explode('.', $event))->toHaveCount(3, "\"$event\" is not namespace.resource.verb");
    }
});

it('uses only the documented verbs', function () {
    // created / updated / deleted / moved / completed — the same five the JS
    // LiveContract type allows without a note. A verb outside them on one side
    // and not the other is drift that reads as a typo.
    $allowed = ['created', 'updated', 'deleted', 'moved', 'completed'];

    foreach (LiveContract::eventNames() as $event) {
        $verb = explode('.', $event)[2] ?? '';
        // in_array rather than expect()->toContain($verb, $message): Pest reads
        // a second argument to toContain as ANOTHER NEEDLE, so the message
        // itself became something the array had to contain.
        expect(in_array($verb, $allowed, true))->toBeTrue("\"$event\" uses an undocumented verb: $verb");
    }
});

it('invalidates at least one key per event, all inside its namespace', function () {
    foreach (LiveContract::EVENTS as $event => $keys) {
        expect($keys)->not->toBeEmpty("\"$event\" invalidates nothing");

        foreach ($keys as $key) {
            expect($key)->not->toBeEmpty();
            expect($key[0])->toBe(LiveContract::NAMESPACE, "\"$event\" invalidates outside its namespace");
        }
    }
});

it('drops the package prefix from the namespace', function () {
    // `laravel-catalog` as a namespace would make every key start with a
    // segment the client never queries, so nothing would match.
    expect(LiveContract::NAMESPACE)->not->toStartWith('laravel-');
    expect(LiveContract::NAMESPACE)->toBe('catalog');
});

it('exports the same shape the JS side declares', function () {
    $array = LiveContract::toArray();

    expect($array)->toHaveKeys(['namespace', 'channel', 'events']);
    expect($array['events'])->toHaveCount(count(LiveContract::EVENTS));

    foreach ($array['events'] as $entry) {
        expect($entry)->toHaveKeys(['event', 'keys']);
    }
});

it('agrees with the TypeScript contract, event for event', function () {
    // Read out of the TS source rather than through a bundler, so this holds in
    // a plain `composer test` with no Node toolchain present.
    $path = __DIR__.'/../../../fancy-catalog-js/src/live.ts';

    if (! is_file($path)) {
        // Sibling repo absent in a standalone clone. Skipping beats a false
        // failure claiming the contracts differ.
        expect(true)->toBeTrue();

        return;
    }

    $source = (string) file_get_contents($path);

    preg_match_all('/\{\s*event:\s*"([^"]+)"/', $source, $matches);
    $jsEvents = $matches[1] ?? [];

    // Guard the guard: a regex that matched nothing would make the comparison
    // below vacuously true.
    expect($jsEvents)->not->toBeEmpty('parsed no events out of live.ts — the regex missed');

    sort($jsEvents);
    $phpEvents = LiveContract::eventNames();
    sort($phpEvents);

    expect($jsEvents)->toBe($phpEvents);
});
