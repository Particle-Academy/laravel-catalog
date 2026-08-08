# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Pre-1.0:** breaking changes land in MINOR releases. Until 1.0 the minor
> number is not a compatibility promise — read the entry, not the version.

> This file starts at 0.9.2. Earlier releases predate it; `git log` is the
> record for those.

## [Unreleased]

## [0.11.0] — 2026-08-07

### Added

- **`LaravelCatalog\LiveContract`** — the PHP half of the catalog's Live
  Contract. Declares which broadcast events this package emits and which client
  query keys each one invalidates, matching `catalogLive` in
  `@particle-academy/fancy-catalog`.

  A parity test on each side asserts the two lists match. That test is the
  point: drift between a mirror pair is silent, because a renamed event does not
  throw — the browser listens for a name nobody broadcasts, the cache is never
  invalidated, and the UI quietly stops updating.

  **What you must do:** nothing. The constant is additive and nothing reads it
  unless a host wires it up.


## [0.10.0] — 2026-08-07

### Changed

- **BREAKING — PHP 8.2 is no longer supported.** `require.php` moves from `^8.2` to `^8.4`.

  **What you must do:** on PHP 8.4 or newer, nothing. On 8.2, either upgrade PHP first or stay on the previous release — it keeps working and is unaffected by this.

- **BREAKING — Laravel 11 and 12 are no longer supported.** The framework requirement narrows from `^11.0|^12.0|^13.0` to `^13.0`.

  **What you must do:** on Laravel 13, nothing. On 11 or 12, stay on the previous release until you upgrade the framework.

- CI now tests PHP 8.4 with Laravel 13 only, instead of a matrix spanning versions this package no longer claims to support. A matrix that tests what the manifest forbids is worse than none — it reports green for a combination nobody can install.

### Why

These are the kit 0.5 platform floors. The suite was split across PHP 8.2 and 8.3 with the framework spanning 11–13, so no package could rely on anything newer than its weakest sibling. Every PHP package in the kit takes the same floors at once, so a consumer never has to resolve a mix.

Pre-1.0, so this lands in a MINOR. **No API changed, nothing was removed, nothing was renamed** — only what the package requires.


## [0.9.5] — 2026-07-31

### Fixed

- **The `add_lookup_key_to_catalog_tables` migration now reads
  `config('catalog.tables.*')` instead of naming `products` / `prices`
  literally.** Shipped that way in 0.9.4, it broke `migrate:fresh` outright for
  every install that renames or prefixes the catalog tables — the documented,
  supported configuration that every other migration in this package already
  honoured.

  **What you must do:**

  - **Default table names (`products` / `prices`) — nothing.** The migration
    resolves to exactly the same names it used before.
  - **Prefixed or renamed tables — upgrade and run `php artisan migrate`.** The
    0.9.4 run failed before recording itself, so the migration is still pending
    and applies cleanly against your real tables. If you held back at 0.9.1
    because of this, 0.9.4's laravel-fms range widening (and with it
    laravel-fms 0.8.0) is now reachable.
  - **Prefixed tables *and* MySQL — check your own `products` table for a stray
    `lookup_key`.** MySQL does not roll DDL back, so the first half of the
    broken migration committed: the column and its unique index landed on
    whatever unprefixed `products` table you had — very likely your
    application's own, which is the reason to prefix in the first place. Drop
    the index and the column if you find them. Postgres and SQLite rolled the
    whole migration back, so there is nothing to clean up there.

  Reported in [#7](https://github.com/Particle-Academy/laravel-catalog/issues/7).

- **The migration self-skips instead of failing when there is nothing to do.**
  The create migrations already defer to the consumer when a foreign-key target
  is missing at apply time, so a catalog table can legitimately be absent when
  this one runs — and `Schema::table()` on a missing table is a hard error, not
  the no-op that case calls for. It now skips a missing table and a column that
  is already present, matching the self-skip the rest of the package documents.

- **Rollback drops the right index on a prefixed install.** `down()` derived the
  unique index name from the literal table name, so it looked for
  `products_lookup_key_unique` where Laravel had created
  `catalog_products_lookup_key_unique`. It now derives it from the resolved name.

### Added

- **A test suite.** The package shipped none, which is why a hardcoded table
  name reached a release. `composer install && vendor/bin/pest` covers the
  migrations, and CI now runs it on every push and PR.

  The migration tests run against **non-default** table names on purpose: a
  migration test that only exercises the defaults passes whether or not the
  config is ever read, and would have passed against the 0.9.4 bug. Eight of the
  nine fail against the pre-fix migration, with the reporter's exact error.

### Documentation

- **Added "Fulfilling Payments".** The checkout docs ended at the redirect to
  Stripe, and nothing in the README or this changelog mentioned webhooks — so
  the obvious reading was that `successUrl` means paid. It does not: a customer
  who pays and closes the tab never reaches it, and reaching it does not mean
  the payment cleared. Following the docs literally shipped either unfulfilled
  paid orders or free product, and neither raises an error.

  The new section documents what was already true but undiscoverable: this
  package depends on Cashier, which already serves a signed `POST /stripe/webhook`
  and dispatches `WebhookReceived` for every payload type — so one-time
  fulfilment is a listener, not a route and signature check of your own. It also
  covers the three things that are easy to get wrong: checking `payment_status`
  rather than a `complete` session status, putting your own id in the checkout
  `metadata` (the `metadata:` parameter existed but no example used it), and
  making fulfilment idempotent against Stripe's retries and the redirect race.

  Reported from an integration that hit all three.

## [0.9.4] — 2026-07-28

### Changed

- Widened the `particle-academy/laravel-fms` requirement from `^0.2|…|^0.8` to `>=0.2 <2.0`, so a sibling
  minor release is an upgrade and not a resolver conflict. **No action needed** —
  widening a range only adds candidates; the version you have today still resolves.

  A caret on a `0.x` range locks the MINOR, so every one of these pinned a
  sibling at whatever it happened to be on the day it was written, and each
  sibling release then read as a conflict to Composer/npm rather than an
  upgrade. Nothing in this package was using an API the newer minors removed
  — the range was the whole problem.

## [0.9.3] — 2026-07-27

### Changed

- **Allow `particle-academy/laravel-fms` `^0.8`.** 0.8.0 corrects the `usage` /
  `remaining` callback signature to `($user, $context)`. This package defines no
  such callbacks, so the change does not reach it — but the old ceiling would
  have made 0.8.0 unreachable for anyone installing both.

## [0.9.2] — 2026-07-27

### Fixed

- **`lookup_key` was used everywhere and never existed as a column.** It was in
  `$fillable` on Product and Price, cast on Product, exposed through a
  `lookupKey()` accessor, and read during Stripe sync — and no migration created
  it on either table.

  Two failure modes, and the second is the one that mattered:

  1. `Product::create([... 'lookup_key' => 'x'])` threw — no such column.
  2. **Reading failed silently.** `lookupKey()` returned null forever, so every
     synced product wrote `product_lookup_key => null` into its Stripe metadata.
     Nothing errored; the metadata was quietly useless.

  Reported by
  [GuardCard.net](https://github.com/Particle-Academy/laravel-catalog/issues/4),
  who hit it integrating catalog + fms.

  **What you have to do: nothing.** Run `php artisan migrate`. The column is
  nullable and NULLs do not collide in a unique index on any supported driver,
  so existing rows are untouched and there is nothing to backfill.

- **Price lookup keys never reached Stripe's own `lookup_key` field.** The value
  was written into Stripe *metadata* only. Stripe Prices support `lookup_key`
  natively, and `prices.list(lookup_keys: [...])` reads only the real field — so
  the one thing a lookup key is for did not work, even once the column existed.

  Prices are immutable, so this package archives a price and creates a
  replacement whenever the amount or interval changes. The replacement now
  carries `transfer_lookup_key`, without which that create fails with
  *"lookup key already exists"* the first time anyone changes the price of
  something that has a key. **Fixing the column alone would have introduced that
  failure**, which is why both land together.

  Changing *only* the lookup key leaves pricing untouched and so takes the
  metadata-update path; that path now sends the key too, instead of saving it
  locally and never transmitting it.

### Added

- **This changelog.** The package had none.

### Notes

`lookup_key` is not a second `external_id`, and the two are close to opposites:

| | |
|---|---|
| `external_id` | **Stripe's** opaque id (`prod_…` / `price_…`), assigned by Stripe and saved back after a sync. |
| `lookup_key` | **Yours** — a stable, human-readable handle so application code says `pro-monthly` rather than a ULID or a Stripe id that changes. |

That distinction is what makes it load-bearing: a price change mints a new Stripe
id, and the lookup key is the thing that survives the swap.
