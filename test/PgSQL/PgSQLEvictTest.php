<?php

namespace Vectorial1024\LaravelCacheEvict\Test\PgSQL;

use Illuminate\Support\Facades\Config;
use PDO;
use PDOException;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\Test\Core\AbstractDatabaseCacheEvictTestCase;

class PgSQLEvictTest extends AbstractDatabaseCacheEvictTestCase
{
    private PDO|null $pdo;

    private string $dbHost = '127.0.0.1';
    private string $dbUser = 'postgres';
    private string $dbPass = 'postgres';

    protected function setUpCache(): void
    {
        $dbHost = $this->dbHost;
        $dbUser = $this->dbUser;
        $dbPass = $this->dbPass;

        try {
            $this->pdo = new PDO("pgsql:host=$dbHost", $dbUser, $dbPass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $x) {
            $this->fail("Could not use PDO: " . $x->getMessage());
        }

        $this->pdo->exec("CREATE DATABASE laravel");
        // postgresql works by specifying the database during connection
        $this->pdo = null;
        try {
            $this->pdo = new PDO("pgsql:host=$dbHost;dbname=laravel", $dbUser, $dbPass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $x) {
            $this->fail("Could not use PDO: " . $x->getMessage());
        }

        $this->pdo->exec(<<<SQL
            CREATE TABLE cache (
                "key"	VARCHAR(255) NOT NULL,
                value	TEXT NOT NULL,
                expiration	INTEGER NOT NULL,
            PRIMARY KEY("key"))
SQL);
        $this->pdo->exec(<<<SQL
            CREATE TABLE cache_locks (
                "key" VARCHAR(255) NOT NULL,
                owner VARCHAR(255) NOT NULL,
                expiration INTEGER NOT NULL,
            PRIMARY KEY ("key"))
SQL);

        Config::set('database.connections.pgsql.driver', 'pgsql');
        Config::set('database.connections.pgsql.host', '127.0.0.1');
        Config::set('database.connections.pgsql.port', '5432');
        Config::set('database.connections.pgsql.database', 'laravel');
        Config::set('database.connections.pgsql.username', $dbUser);
        Config::set('database.connections.pgsql.password', $dbPass);
        Config::set('database.connections.pgsql.charset', 'utf8');

        // then, database cache
        $this->configureDatabaseCacheStoreForPrefix('database');
    }

    protected function tearDownCache(): void
    {
        // drop database
        // postgresql cannot drop the currently open database, so reconnect emptily and then drop database
        $this->pdo = null;
        $dbHost = $this->dbHost;
        $dbUser = $this->dbUser;
        $dbPass = $this->dbPass;
        try {
            $this->pdo = new PDO("pgsql:host=$dbHost", $dbUser, $dbPass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $x) {
            $this->fail("Could not use PDO: " . $x->getMessage());
        }
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
        Config::set("cache.stores.$cacheName.connection", 'pgsql');
        Config::set("cache.stores.$cacheName.lock_connection", '');
        if ($intendedPrefix !== null) {
            Config::set("cache.stores.$cacheName.prefix", $intendedPrefix);
        }
    }
}
