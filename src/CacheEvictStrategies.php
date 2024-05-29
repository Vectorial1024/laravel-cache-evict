<?php

namespace Vectorial1024\LaravelCacheEvict;

/**
 * The static class that remembers how to evict caches of various cache drivers.
 */
class CacheEvictStrategies
{
    public const DRIVER_MEMCACHED = 'memcached';

    public const DRIVER_REDIS = 'redis';

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
     * Returns the eviction strategy for the given cache driver.
     * @throws EvictionRefusedFeatureExistsException thrown when we refuse to handle the given driver because it has its own eviction manager
     */
    public static function getEvictionStrategy(string $driverName)
    {
        if (isset(self::$wontDoMap[$driverName])) {
            throw new EvictionRefusedFeatureExistsException();
        }
    }
}
