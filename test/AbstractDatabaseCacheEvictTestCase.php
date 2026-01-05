<?php

namespace Vectorial1024\LaravelCacheEvict\Test;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Wilderborn\Partyline\ServiceProvider;

/**
 * The base class to test our various database-specific cache eviction.
 * We mainly test for cache prefixes and key iteration, but this may include other aspects.
 */
abstract class AbstractDatabaseCacheEvictTestCase extends AbstractCacheEvictTestCase
{
    protected string $prefixRelated;
    protected string $prefixUnrelated;

    protected function setUpCachePrefixKeys(): void
    {
        $theNumber = mt_rand(1, 100);
        $this->prefixRelated = "prefix" . $theNumber;
        $this->prefixUnrelated = "prefix" . $theNumber;
    }

    abstract function configureDatabaseCacheStoreForPrefix(string $cacheName, string $intendedPrefix): void;

    public function testDatabaseKeyWalking(): void
    {
        // test that our evictor doesn't hang when iterating over the database table
        // note: hopefully the CI/CD pipeline will kill hanging scripts

        $storeName = $this->getStoreName();
        $cacheDriver = $this->getCacheDriverName();
        $store = Cache::store($storeName);

        // generate some random items for the database
        $pickedKeys = [];
        for ($i = 0; $i < 5; $i++) {
            $theKey = $this->generateRandomKey();
            $pickedKeys[] = $theKey;
            $theValue = md5($theKey);
            $store->delete($theKey);
            $store->put($theKey, $theValue, 1);
        }

        // now, we wait for it to expire
        sleep(1);
        // to invoke our evictor
        $strategy = CacheEvictStrategies::getEvictionStrategy($storeName, $cacheDriver);
        $strategy->execute();

        // we test that the evictor can progress and can safely reach this trivial block
        // all these should be gone
        foreach ($pickedKeys as $theKey) {
            $this->assertFalse($store->has($theKey));
        }
    }

    public function testDatabasePrefixRespect()
    {
        $this->setUpCachePrefixKeys();

        // generate two stores with different prefixes
        $storeName = $this->getStoreName();
        $cacheDriver = $this->getCacheDriverName();
        $storeNameRelated = $storeName . "related";
        $this->configureDatabaseCacheStoreForPrefix($storeNameRelated, $this->prefixRelated);
        $storeRelated = Cache::store($storeNameRelated);
        $storeNameUnrelated = $storeName . "unrelated";
        $this->configureDatabaseCacheStoreForPrefix($storeNameUnrelated, $this->prefixUnrelated);
        $storeUnrelated = Cache::store($storeNameUnrelated);

        // put values into them
        $keysRelated = [];
        $keysUnrelated = [];
        for ($i = 0; $i < 5; $i++) {
            $theKey = $this->generateRandomKey();
            $keysRelated[] = $theKey;
            $theValue = md5($theKey);
            $storeRelated->delete($theKey);
            $storeRelated->put($theKey, $theValue, 1);
        }
        for ($i = 0; $i < 5; $i++) {
            $theKey = $this->generateRandomKey();
            $keysUnrelated[] = $theKey;
            $theValue = md5($theKey);
            $storeUnrelated->delete($theKey);
            // expire longer to make them survive ttl eviction
            $storeUnrelated->put($theKey, $theValue, 60);
        }

        // now, we wait for it to expire
        sleep(1);
        // to invoke our evictor on only the related cache
        $strategy = CacheEvictStrategies::getEvictionStrategy($storeName, $cacheDriver);
        $strategy->execute();

        // all related cache items should be gone...
        foreach ($keysRelated as $theKey) {
            $this->assertFalse($storeRelated->has($theKey));
        }
        // but all unrelated cache items should still be here...
        foreach ($keysUnrelated as $theKey) {
            $this->assertTrue($storeUnrelated->has($theKey));
        }
    }
}
