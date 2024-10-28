# laravel-cache-evict
[![Packagist License][packagist-license-image]][packagist-url]
[![Packagist Version][packagist-version-image]][packagist-url]
[![Packagist Downloads][packagist-downloads-image]][packagist-stats-url]
[![PHP Dependency Version][php-version-image]][packagist-url]
[![GitHub Actions Workflow Status][php-build-status-image]][github-actions-url]

Efficiently remove expired cache data in Laravel.

## Situation
As of writing, several Laravel cache drivers do not have automatic removal of expired cached items:

- `file`
- `database`
- `array`

## Why is it a problem?
If you are using some of the above cache drivers, and are caching a lot of short-lived items that are unikely to be used again, then the items that you created will not be removed by Laravel: they might expire, but they still exist in the actual data store. This means your cache is now growing larger and larger, which can be a problem (e.g., running out of disk space).

The `cache:clear` command from Laravel works, but might not be the thing you want. It does not check item expiry (it removes everything), and also clears the Laravel framework cache (e.g. `/bootstrap/cache/*`), which can be especially problematic when you are using the `file` cache driver (consider a case: cache items are created by the `www-data` user but `/bootstrap/cache/*` is owned by the `ubuntu` user).

In this case, this library can help by removing the expired items in your cache and leave the Laravel framework cache untouched. See below sections for more details.

This library is designed to be memory efficient, so even if there are a lot of items in the cache (e.g. you are running this for the first time to deal with an oversized cache), it can still run reasonably well.

# Install
via Composer:

```sh
composer require vectorial1024/laravel-cache-evict
```

## Supported cache types
The following cache drivers from `cache.php` are currently supported:
- `database`
- `file`

Some drivers (e.g. `memcached`, `redis`) will never be supported because they have their own item eviction mechanism. For those drivers, you should use those features instead of using this library.

Obviously, perhaps you are using your own cache driver which this library does not know about, but that you know how to evict the caches. In this case, you may define your own cache eviction strategies and register them to this library (see FAQ section).

# Usage

You may run this in the command line:

```sh
# evicts the default cache in your Laravel app
php artisan cache:evict

# you may also specify the cache to clear; e.g. the file cache defined in cache.php:
php artisan cache:evict file
```

Or, you may put this into your console kernel schedule:

```php
use Vectorial1024\LaravelCacheEvict\CacheEvictCommand;

// note: because this command may have long running time, it is strongly recommended to run this command in the background
// this avoids accidentally delaying other scheduled tasks

// evicts the default cache in your Laravel app
Schedule::command(CacheEvictCommand::class)->daily()->runInBackground();

// you may also specify the cache to clear; e.g. the file cache defined in cache.php:
Schedule::command(CacheEvictCommand::class, ['target' => 'file'])->daily()->runInBackground();
```

## The relationship with `cache.php`
This library checks the cache *name* (not *driver*!) inside `cache.php` to determine which cache to clear. This means, if you have the following `cache.php` ...

```php
[
    'stores' => [
        'local_store' => [
            'driver' => 'file',
            // other config...
        ],

        'another_store' => [
            'driver' => 'file',
            // other config...
        ],
    ],
]
```

... and you run the command like this ...

```sh
php artisan cache:evict local_store
```

... then, you will only evict the `local_store` cache. The library will then notice `local_store` is using the `file` driver, and will evict items using the file eviction strategy.

The `another_store` cache is unaffected, assuming you configured both to store the cache data at different directories.

# Frequently-asked questions (FAQ)

## How to define custom eviction strategies?
You can do so inside your Laravel service provider. Simply do the following:

```php
public function boot()
{
    // register a handler for a specific cache driver
    // YourEvictStrategy extends Vectorial1024\LaravelCacheEvict\AbstractEvictStrategy
    CacheEvictStrategies::registerDriverStrategy('your_driver_name', YourEvictStrategy::class);

    // or, register that a specific cache driver should not be handled because it has its own handler already
    CacheEvictStrategies::registerDriverRefusedBecauseFeatureExists('self_managed_driver_name');
}
```

## Will this library help me reclaim `database` disk spaces?
No, but if you are using this library regularly to evict expired items, then you do not need to worry about reclaiming free space. This topic is best discussed with a system admin/database specialist.

[packagist-url]: https://packagist.org/packages/vectorial1024/laravel-cache-evict
[packagist-stats-url]: https://packagist.org/packages/vectorial1024/laravel-cache-evict/stats
[github-actions-url]: https://github.com/Vectorial1024/laravel-cache-evict/actions/workflows/php.yml

[packagist-license-image]: https://img.shields.io/packagist/l/vectorial1024/laravel-cache-evict?style=plastic
[packagist-version-image]: https://img.shields.io/packagist/v/vectorial1024/laravel-cache-evict?style=plastic
[packagist-downloads-image]: https://img.shields.io/packagist/dm/vectorial1024/laravel-cache-evict?style=plastic
[php-version-image]: https://img.shields.io/packagist/dependency-v/vectorial1024/laravel-cache-evict/php?style=plastic&label=PHP
[php-build-status-image]: https://img.shields.io/github/actions/workflow/status/Vectorial1024/laravel-cache-evict/php.yml?style=plastic
