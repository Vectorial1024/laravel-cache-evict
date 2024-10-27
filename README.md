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

# Usage

You may run this in the command line:

```sh
php artisan cache:evict
```

Or, you may put this into your console kernel schedule:

(WIP)

## Command line arguments
```
cache:evict {target?}
```

`target` (optional): the Laravel cache to evict items; this is the name of the array key in your Laravel `cache.php` file, inside the `stores` array.

If `target` is not provided, then the default Laravel cache will be targetted.
