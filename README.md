# laravel-cache-evict
(badges here)

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

(WIP)

## Supported cache types
The following cache drivers from `cache.php` are currently supported:
- `database`
- `file`

Some drivers (e.g. `memcached`, `redis`) will never be supported because they have their own item eviction mechanism. For those drivers, you should use those features instead of using this library.

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
