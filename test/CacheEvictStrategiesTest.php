<?php

namespace Vectorial1024\LaravelCacheEvict\Test;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use SQLite3;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\Database\DatabaseEvictStrategy;
use Vectorial1024\LaravelCacheEvict\EvictionRefusedFeatureExistsException;
use Vectorial1024\LaravelCacheEvict\File\FileEvictStrategy;
use Wilderborn\Partyline\ServiceProvider;

/**
 * This test class handles general test cases of this package,
 * e.g. whether it may correctly determine which eviction strategy to use.
 */
class CacheEvictStrategiesTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        CacheEvictStrategies::initOrReset();
    }

    protected function getPackageProviders($app)
    {
        // load required package providers for partyline to work
        return [
            ServiceProvider::class
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

        $strategy = CacheEvictStrategies::getEvictionStrategy('database', CacheEvictStrategies::DRIVER_DATABASE);
        $this->assertInstanceOf(DatabaseEvictStrategy::class, $strategy);

        // correctly handle unknown cases
        $strategy = CacheEvictStrategies::getEvictionStrategy('foo', 'bar');
        $this->assertNull($strategy);
    }

    public function testNoOverridingStrategies()
    {
        // once set, eviction strategies may not be replaced
        // note to users: either you set your custom strategies correctly, or (in case the default strategies have problems) open issues/PRs to the repo
        CacheEvictStrategies::registerDriverStrategy(CacheEvictStrategies::DRIVER_FILE, DatabaseEvictStrategy::class);
        $strategy = CacheEvictStrategies::getEvictionStrategy('file', CacheEvictStrategies::DRIVER_FILE);
        $this->assertInstanceOf(FileEvictStrategy::class, $strategy);
    }

    public function testDefineCustomStrategies()
    {
        // create a new (fake) file cache store
        Config::set('cache.stores.file2.driver', 'file2');
        Config::set('cache.stores.file2.path', Config::get('cache.stores.file.path'));
        Config::set('cache.stores.file2.lock_path', Config::get('cache.stores.file.lock_path'));
        CacheEvictStrategies::registerDriverStrategy('file2', FileEvictStrategy::class);
        $strategy = CacheEvictStrategies::getEvictionStrategy('file2', 'file2');
        $this->assertInstanceOf(FileEvictStrategy::class, $strategy);
        // then unset them
        Config::set('cache.stores.file2.driver', null);
        Config::set('cache.stores.file2.path', null);
        Config::set('cache.stores.file2.lock_path', null);
    }

    // ---

    public function call($method, $uri, $parameters = [], $files = [], $server = [], $content = null, $changeHistory = true)
    {
        // pass
        return;
    }

    public function be(Authenticatable $user, $driver = null)
    {
        // pass
        return;
    }

    public function seed($class = 'DatabaseSeeder')
    {
        // pass
        return;
    }

    public function artisan($command, $parameters = [])
    {
        // pass
        return;
    }
}
