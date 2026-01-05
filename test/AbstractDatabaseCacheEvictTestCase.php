<?php

namespace Vectorial1024\LaravelCacheEvict\Test;

use Illuminate\Support\Facades\Cache;
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

    public function testDatabaseKeyWalking(): void
    {
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
}
