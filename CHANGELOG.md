# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Pre-1.0:** breaking changes land in MINOR releases. Until 1.0 the minor
> number is not a compatibility promise — read the entry, not the version.

> This file starts at 0.9.2. Earlier releases predate it; `git log` is the
> record for those.

## [Unreleased]

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

  Reported by [GuardCard.net](https://github.com/Particle-Academy/laravel-catalog/issues/4),
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
