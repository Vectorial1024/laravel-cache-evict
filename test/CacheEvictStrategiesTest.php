<?php

namespace Vectorial1024\LaravelCacheEvict\Test;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\EvictionRefusedFeatureExistsException;
use Vectorial1024\LaravelCacheEvict\File\FileEvictStrategy;

class CacheEvictStrategiesTest extends TestCase
{
    public function setUp(): void
    {
        CacheEvictStrategies::initOrReset();

        // set up cache configs

        // file cache
        $fileCacheDir = dirname(dirname(__FILE__))."/storage/framework/cache/data";
        Config::set('cache.stores.file.driver', 'file');
        Config::set('cache.stores.file.path', $fileCacheDir);
        Config::set('cache.stores.file.lock_path', $fileCacheDir);
    }

    public function testCorrectPresetStrategies()
    {
        // store name does not matter at the moment
        $this->expectException(EvictionRefusedFeatureExistsException::class);
        $strategy = CacheEvictStrategies::getEvictionStrategy('', CacheEvictStrategies::DRIVER_MEMCACHED);

        $this->expectException(EvictionRefusedFeatureExistsException::class);
        $strategy = CacheEvictStrategies::getEvictionStrategy('', CacheEvictStrategies::DRIVER_REDIS);

        // mock as if we have some proper config
        $strategy = CacheEvictStrategies::getEvictionStrategy('file', CacheEvictStrategies::DRIVER_FILE);
        $this->assertInstanceOf(FileEvictStrategy::class, $strategy);
    }

    public function testFileCacheEviction()
    {
        $testNumber = mt_rand();
        $testKey = "k$testNumber";
        $testValue = md5($testNumber);
        $store = Cache::store("file");

        // first ensure it does not already exist
        $store->delete($testKey);
        $this->assertFalse($store->has($testKey));

        // then put the key-vslue in it with 1 second of expiration
        $store->put($testKey, $testValue, 1);
        $this->assertTrue($store->has($testKey));

        // now, we wait for it to expire
        sleep(1);
        // to invoke our evictor
        $strategy = CacheEvictStrategies::getEvictionStrategy('file', CacheEvictStrategies::DRIVER_FILE);
        $strategy->execute();

        // now, there should have no such item again
        $this->assertFalse($store->has($testKey));
    }
}
