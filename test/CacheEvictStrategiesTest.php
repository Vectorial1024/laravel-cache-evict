<?php

namespace Vectorial1024\LaravelCacheEvict\Test;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use SQLite3;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\Database\DatabaseEvictStrategy;
use Vectorial1024\LaravelCacheEvict\EvictionRefusedFeatureExistsException;
use Vectorial1024\LaravelCacheEvict\File\FileEvictStrategy;

class CacheEvictStrategiesTest extends TestCase
{
    private string $sqliteDbName;
    private SQLite3 $sqliteDb;

    public function setUp(): void
    {
        parent::setUp();

        CacheEvictStrategies::initOrReset();

        // set up cache configs
        // these configs are basically copied over from the laravel default settings
        $projectRoot = dirname(dirname(__FILE__));

        // file cache
        $fileCacheDir = "$projectRoot/storage/framework/cache/data";
        Config::set('cache.stores.file.driver', 'file');
        Config::set('cache.stores.file.path', $fileCacheDir);
        Config::set('cache.stores.file.lock_path', $fileCacheDir);

        // sqlite database
        // we need to set up an SQLite db file on disk, so that the test script can correctly use it
        $this->sqliteDbName = "$projectRoot/database/database.sqlite";
        $this->sqliteDb = new SQLite3($this->sqliteDbName);
        $this->sqliteDb->exec(<<<SQL
            CREATE TABLE "cache" (
                "key"	varchar NOT NULL,
                "value"	TEXT NOT NULL,
                "expiration"	INTEGER NOT NULL,
            PRIMARY KEY("key"))
        SQL);
        $this->sqliteDb->exec(<<<SQL
            CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"))
        SQL);
        Config::set('database.connections.sqlite.driver', 'sqlite');
        Config::set('database.connections.sqlite.url', '');
        Config::set('database.connections.sqlite.database', $this->sqliteDbName);
        Config::set('database.connections.sqlite.prefix', '');
        Config::set('database.connections.sqlite.foreign_key_constraints', true);

        // then, database cache
        Config::set('cache.stores.database.driver', 'database');
        Config::set('cache.stores.database.table', 'cache');
        Config::set('cache.stores.database.connection', 'sqlite');
        Config::set('cache.stores.database.lock_connection', '');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->sqliteDb->close();
        unlink($this->sqliteDbName);
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

        $strategy = CacheEvictStrategies::getEvictionStrategy('database', CacheEvictStrategies::DRIVER_DATABASE);
        $this->assertInstanceOf(DatabaseEvictStrategy::class, $strategy);
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

    #[DataProvider('cacheCasesProvider')]
    public function testCorrectCacheEviction(string $storeName, string $cacheDriver): void
    {
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

    public static function cacheCasesProvider(): array
    {
        // store name, cache driver
        return [
            "File cache" => ['file', CacheEvictStrategies::DRIVER_FILE],
            "Database cache" => ['database', CacheEvictStrategies::DRIVER_DATABASE],
        ];
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
