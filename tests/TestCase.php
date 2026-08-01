<?php

namespace LaravelCatalog\Tests;

use LaravelCatalog\CatalogServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [CatalogServiceProvider::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Run ONLY this package's migrations.
     *
     * Deliberately not `artisan migrate` with no path: Cashier is a hard
     * dependency and registers its own migrations, which alter a `users` table
     * that does not exist in a package test app. Scoping to our directory keeps
     * these tests about our schema.
     */
    protected function migrateCatalog(): void
    {
        $this->artisan('migrate', [
            '--path' => realpath(__DIR__.'/../database/migrations'),
            '--realpath' => true,
        ])->run();
    }

    protected function rollbackCatalog(): void
    {
        $this->artisan('migrate:rollback', [
            '--path' => realpath(__DIR__.'/../database/migrations'),
            '--realpath' => true,
        ])->run();
    }
}
