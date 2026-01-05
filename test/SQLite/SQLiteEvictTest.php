<?php

namespace Vectorial1024\LaravelCacheEvict\Test\SQLite;

use Illuminate\Support\Facades\Config;
use SQLite3;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\Test\AbstractDatabaseCacheEvictTestCase;

class SQLiteEvictTest extends AbstractDatabaseCacheEvictTestCase
{
    private string $sqliteDbName;

    protected function setUpCache(): void
    {
        // we need to set up an SQLite db file on disk, so that the test script can correctly use it
        $projectRoot = $this->getProjectRoot();

        $this->sqliteDbName = "$projectRoot/database/database.sqlite";
        $sqliteDb = new SQLite3($this->sqliteDbName);
        $sqliteDb->exec('DROP TABLE IF EXISTS "cache"');
        $sqliteDb->exec(<<<SQL
            CREATE TABLE "cache" (
                "key"	varchar NOT NULL,
                "value"	TEXT NOT NULL,
                "expiration"	INTEGER NOT NULL,
            PRIMARY KEY("key"))
        SQL);
        $sqliteDb->exec('DROP TABLE IF EXISTS "cache_locks"');
        $sqliteDb->exec(<<<SQL
            CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"))
        SQL);
        $sqliteDb->close();
        Config::set('database.connections.sqlite.driver', 'sqlite');
        Config::set('database.connections.sqlite.url', '');
        Config::set('database.connections.sqlite.database', $this->sqliteDbName);
        Config::set('database.connections.sqlite.prefix', '');
        Config::set('database.connections.sqlite.foreign_key_constraints', true);

        // then, database cache
        $this->configureDatabaseCacheStoreForPrefix('database');
    }

    protected function tearDownCache(): void
    {
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

    function configureDatabaseCacheStoreForPrefix(string $cacheName, string|null $intendedPrefix = null): void
    {
        Config::set("cache.stores.$cacheName.driver", 'database');
        Config::set("cache.stores.$cacheName.table", 'cache');
        Config::set("cache.stores.$cacheName.connection", 'sqlite');
        Config::set("cache.stores.$cacheName.lock_connection", '');
        if ($intendedPrefix !== null) {
            Config::set("cache.stores.$cacheName.prefix", $intendedPrefix);
        }
    }
}
