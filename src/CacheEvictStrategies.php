<?php

namespace Vectorial1024\LaravelCacheEvict;

use Vectorial1024\LaravelCacheEvict\File\FileEvictStrategy;

/**
 * The static class that remembers how to evict caches of various cache drivers.
 */
class CacheEvictStrategies
{
    public const DRIVER_MEMCACHED = 'memcached';

    public const DRIVER_REDIS = 'redis';

    public const DRIVER_FILE = 'file';

    /**
     * @var array<string, class-string> the map of driver to eviction strategy
     */
    protected static array $strategyMap = [];

    /**
     * @var array<string, true> the list-map of drivers that we will not do because they already can evict keys by themselves.
     */
    protected static array $wontDoMap = [];

    /**
     * Resets the strategies to the default values.
     * 
     * This may be useful when doing unit testing.
     */
    public static function initOrReset()
    {
        // reset the memory first
        self::$strategyMap = [];
        self::$wontDoMap = [];

        // and then re-register the default strategies
        self::registerDriverRefusedBecauseFeatureExists(self::DRIVER_MEMCACHED);
        self::registerDriverRefusedBecauseFeatureExists(self::DRIVER_REDIS);

        self::registerDriverStrategy(self::DRIVER_FILE, FileEvictStrategy::class);
    }

    /**
     * Register a certain driver to have a certain eviction strategy, if there are no strategies defined for the given driver yet.
     * @param string $driverName
     * @param class-string $strategyClass
     */
    public static function registerDriverStrategy(string $driverName, string $strategyClass)
    {
        if (isset(self::$strategyMap[$driverName])) {
            return;
        }
        if (!is_subclass_of($strategyClass, AbstractEvictStrategy::class)) {
            throw new \InvalidArgumentException("The provided eviction strategy for '{$driverName}' must extend " . AbstractEvictStrategy::class . ".");
        }
        self::$strategyMap[$driverName] = $strategyClass;
    }

    /**
     * Register a certain cache driver as "won't-do" because said driver has its own eviction management system.
     * 
     * Example of this type of drivers: "redis".
     * @param string $driverName
     */
    public static function registerDriverRefusedBecauseFeatureExists(string $driverName)
    {
        if (!isset(self::$wontDoMap[$driverName])) {
            self::$wontDoMap[$driverName] = true;
        }
    }

    /**
     * Returns the eviction strategy for the given cache store and the cache driver.
     * @param string $storeName the name of the cache store
     * @param string $driverName the name of the cache driver
     * @throws EvictionRefusedFeatureExistsException thrown when we refuse to handle the given driver because it has its own eviction manager
     */
    public static function getEvictionStrategy(string $storeName, string $driverName): ?AbstractEvictStrategy
    {
        if (isset(self::$wontDoMap[$driverName])) {
            throw new EvictionRefusedFeatureExistsException();
        }
        if (isset(self::$strategyMap[$driverName])) {
            $className = self::$strategyMap[$driverName];
            return new $className($storeName);
        }
        // what is this?
        return null;
    }
}
