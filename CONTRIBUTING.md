# Contributing to particle-academy/laravel-catalog

Notes for anyone, human or agent, working ON this package's code. How to USE the
package is in [AGENTS.md](./AGENTS.md); what it is, is in [README.md](./README.md).
`CLAUDE.md` is a symlink to this file, so Claude Code reads these notes when it
works in this repo.

## Setup and tests

```bash
composer install
vendor/bin/pest
```

Tests are Pest, under `tests/Feature` and `tests/Unit` (`phpunit.xml` defines
both suites). The migration tests run against prefixed `catalog_*` table names,
not the defaults, so a change to the migrations or to `catalog.tables` is
covered for a prefixed install.

## Layout

`src/` holds the models (`Product`, `Price`, `ProductFeature`), the services
(`StripeCatalogService`, `StripeCheckoutService`), the `SyncProductToStripe`
job, the `ProductSyncedToStripe` event, the `Catalog` facade, `CatalogManager`
and `CatalogServiceProvider`. Migrations, factories and seeders are under
`database/`; the config is `config/catalog.php`.

## Changelog

Update `CHANGELOG.md` (`[Unreleased]`) in the same commit as the change.
