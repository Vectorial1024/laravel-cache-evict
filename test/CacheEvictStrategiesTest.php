<?php

namespace Vectorial1024\LaravelCacheEvict\Test;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\EvictionRefusedFeatureExistsException;
use Vectorial1024\LaravelCacheEvict\File\FileEvictStrategy;

class CacheEvictStrategiesTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        CacheEvictStrategies::initOrReset();

        // set up cache configs

        // file cache
        $fileCacheDir = dirname(dirname(__FILE__))."/storage/framework/cache/data";
        Config::set('cache.stores.file.driver', 'file');
        Config::set('cache.stores.file.path', $fileCacheDir);
        Config::set('cache.stores.file.lock_path', $fileCacheDir);
    }

    protected function getPackageProviders($app)
    {
        // load required package providers for partyline to work
        return [
            \Wilderborn\Partyline\ServiceProvider::class
        ];
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
        // generate two sets of key-value pair: one is expired, another is not
        // to test our eviction really removes the correct expired item

        $testNumber = mt_rand();
        $testKeyExpire = "k$testNumber";
        $testValueExpire = md5($testNumber);
        $testNumber = mt_rand();
        $testKeyForever = "k$testNumber";
        $testValueForever = md5($testNumber);
        $store = Cache::store("file");

        // first ensure both do not already exist
        $store->delete($testKeyExpire);
        $this->assertFalse($store->has($testKeyExpire));
        $store->delete($testKeyForever);
        $this->assertFalse($store->has($testKeyForever));

        // then put the key-value in it that quickly expires
        $store->put($testKeyExpire, $testValueExpire, 1);
        $this->assertTrue($store->has($testKeyExpire));
        // and another key-value in it that does not expire
        $store->forever($testKeyForever, $testValueForever);
        $this->assertTrue($store->has($testKeyForever));

        // now, we wait for it to expire
        sleep(1);
        // to invoke our evictor
        $strategy = CacheEvictStrategies::getEvictionStrategy('file', CacheEvictStrategies::DRIVER_FILE);
        $strategy->execute();

        // now, only the forever item should exist
        $this->assertFalse($store->has($testKeyExpire));
        $this->assertTrue($store->has($testKeyForever));

        // and then we clean up the thing
        $store->delete($testKeyForever);
        $this->assertFalse($store->has($testKeyForever));
        // for absolute cleanliness, invoke our evictor again to clean up the empty directories
        $strategy->execute();
    }
}
