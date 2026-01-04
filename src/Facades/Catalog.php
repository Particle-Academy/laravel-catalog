<?php

namespace LaravelCatalog\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Catalog Facade
 * Created to provide easy access to catalog functionality without requiring UI components.
 */
class Catalog extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'catalog';
    }
}

