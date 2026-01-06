<?php

namespace Vectorial1024\LaravelCacheEvict\Test\MySQL;

use Illuminate\Support\Facades\Config;
use PDO;
use PDOException;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\Test\AbstractDatabaseCacheEvictTestCase;

class MySQLEvictTest extends AbstractDatabaseCacheEvictTestCase
{
    private PDO|null $pdo;
    
    protected function setUpCache(): void
    {
        $dbHost = '127.0.0.1';
        $dbUser = 'testuser';
        $dbPass = 'testpass';

        try {
            $this->pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $x) {
            $this->fail("Could not use PDO: " . $x->getMessage());
        }

        $this->pdo->exec("CREATE DATABASE laravel");
        $this->pdo->exec("USE laravel");

        $this->pdo->exec(<<<SQL
            CREATE TABLE cache (
                "key"	VARCHAR(255) NOT NULL,
                "value"	TEXT NOT NULL,
                "expiration"	INTEGER NOT NULL,
            PRIMARY KEY("key"))
SQL);
        $this->pdo->exec(<<<SQL
            CREATE TABLE cache_locks (
                "key" VARCHAR(255) NOT NULL,
                "owner" VARCHAR(255) NOT NULL,
                "expiration" INTEGER NOT NULL,
            PRIMARY KEY ("key"))
SQL);

        Config::set('database.connections.mysql.driver', 'mysql');
        Config::set('database.connections.mysql.host', '127.0.0.1');
        Config::set('database.connections.mysql.port', '3306');
        Config::set('database.connections.mysql.database', 'laravel');
        Config::set('database.connections.mysql.username', $dbUser);
        Config::set('database.connections.mysql.password', $dbPass);
        Config::set('database.connections.mysql.charset', 'utf8mb4');
        Config::set('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        // then, database cache
        $this->configureDatabaseCacheStoreForPrefix('database');
    }

    protected function tearDownCache(): void
    {
        // drop database
        $this->pdo->exec("DROP DATABASE laravel");

        // close connection
        $this->pdo = null;
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
