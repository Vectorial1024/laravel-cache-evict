<?php

namespace Vectorial1024\LaravelCacheEvict\Test;

use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Wilderborn\Partyline\ServiceProvider;

/**
 * The base class to test our various cache-specific behaviors.
 */
abstract class AbstractCacheEvictStrategyTest extends TestCase
{
    public function getProjectRoot(): string
    {
        return dirname(__FILE__, 2);
    }

    public function setUp(): void
    {
        parent::setUp();

        // configure the strategies
        CacheEvictStrategies::initOrReset();

        // cache-specific set-up
        $this->setUpCache();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->tearDownCache();
    }

    protected function getPackageProviders($app)
    {
        // load required package providers for partyline to work
        return [
            ServiceProvider::class
        ];
    }

    public function testCorrectCacheEviction(): void
    {
        $storeName = $this->getStoreName();
        $cacheDriver = $this->getCacheDriverName();

        // generate two sets of key-value pair: one is expired, another is not
        // to test our eviction really removes the correct expired item

        $testNumber = mt_rand();
        $testKeyExpire = "k$testNumber";
        $testValueExpire = md5($testNumber);
        $testNumber = mt_rand();
        $testKeyForever = "k$testNumber";
        $testValueForever = md5($testNumber);
        $store = Cache::store($storeName);

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
        $strategy = CacheEvictStrategies::getEvictionStrategy($storeName, $cacheDriver);
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

    abstract protected function setUpCache(): void;

    abstract protected function tearDownCache(): void;

    abstract protected function getStoreName(): string;

    abstract protected function getCacheDriverName(): string;
}