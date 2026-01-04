<?php

namespace Vectorial1024\LaravelCacheEvict\Test\SQLite;

use Illuminate\Support\Facades\Config;
use SQLite3;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\Test\AbstractCacheEvictTestCase;

class SQLiteEvictTest extends AbstractCacheEvictTestCase
{
    private string $sqliteDbName;
    private SQLite3 $sqliteDb;

    protected function setUpCache(): void
    {
        // we need to set up an SQLite db file on disk, so that the test script can correctly use it
        $projectRoot = $this->getProjectRoot();

        $this->sqliteDbName = "$projectRoot/database/database.sqlite";
        $this->sqliteDb = new SQLite3($this->sqliteDbName);
        $this->sqliteDb->exec('DROP TABLE IF EXISTS "cache"');
        $this->sqliteDb->exec(<<<SQL
            CREATE TABLE "cache" (
                "key"	varchar NOT NULL,
                "value"	TEXT NOT NULL,
                "expiration"	INTEGER NOT NULL,
            PRIMARY KEY("key"))
        SQL);
        $this->sqliteDb->exec('DROP TABLE IF EXISTS "cache_locks"');
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

    protected function tearDownCache(): void
    {
        $this->sqliteDb->close();
        unlink($this->sqliteDbName);
    }

    protected function getStoreName(): string
    {
        return "database";
    }

    protected function getCacheDriverName(): string
    {
        return CacheEvictStrategies::DRIVER_DATABASE;
    }
}
