<?php

namespace LaravelCatalog\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use LaravelCatalog\CatalogServiceProvider;

/**
 * A hand-built Laravel kernel: laravel/framework only, no orchestra/testbench
 * (the same approach as change-council's and fancy-sign's harnesses). In-memory
 * SQLite, array drivers, blank provider keys: nothing leaves the process, and
 * nothing reaches Stripe.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Artisan output is not mocked: that path needs Mockery, which arrived
     * only through testbench.
     */
    public $mockConsoleOutput = false;

    public function createApplication(): Application
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-catalog-tests';
        foreach (['app', 'config', 'bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs'] as $dir) {
            if (! is_dir($base.'/'.$dir)) {
                mkdir($base.'/'.$dir, 0777, true);
            }
        }
        // An empty .env: the framework reads one, and nothing in it may come from the machine.
        file_put_contents($base.'/.env', '');

        $app = Application::configure(basePath: $base)
            ->withProviders([CatalogServiceProvider::class], withBootstrapProviders: false)
            ->withMiddleware(static function (Middleware $middleware): void {})
            ->withExceptions(static function (Exceptions $exceptions): void {})
            ->create();

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['env'] = 'testing';
            $this->defineEnvironment($app);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function defineEnvironment(Application $app): void
    {
        $config = $app['config'];

        $config->set('app.env', 'testing');
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('mail.default', 'array');
        $config->set('services.anthropic.key', '');
        $config->set('services.openai.key', '');
        $config->set('cashier.key', '');
        $config->set('cashier.secret', '');

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
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
        Artisan::call('migrate', [
            '--path' => realpath(__DIR__.'/../database/migrations'),
            '--realpath' => true,
        ]);
    }

    protected function rollbackCatalog(): void
    {
        Artisan::call('migrate:rollback', [
            '--path' => realpath(__DIR__.'/../database/migrations'),
            '--realpath' => true,
        ]);
    }
}
