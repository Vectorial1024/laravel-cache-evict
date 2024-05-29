<?php

namespace Vectorial1024\LaravelCacheEvict;

use Illuminate\Support\ServiceProvider;

class CacheEvictServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        CacheEvictStrategies::initOrReset();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheEvictCommand::class,
            ]);
        }
    }
}
