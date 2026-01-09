<?php

namespace Vectorial1024\LaravelCacheEvict\Test\SQLSrv;

use Illuminate\Support\Facades\Config;
use PDO;
use PDOException;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\Test\Core\AbstractDatabaseCacheEvictTestCase;

class SQLSrvEvictTest extends AbstractDatabaseCacheEvictTestCase
{
    private PDO|null $pdo = null;

    private string $dbHost = '127.0.0.1';
    private string $dbUser = 'sa';
    private string $dbPass = 'LaravelPassword!2026';

    protected function setUpCache(): void
    {
        $dbHost = $this->dbHost;
        $dbUser = $this->dbUser;
        $dbPass = $this->dbPass;

        // in our CI/CD use case it is not convenient to even drop the database.
        // so the approach is to ensure we have a clean table for testing.

        try {
            $this->pdo = new PDO("sqlsrv:server=(local)", $dbUser, $dbPass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $x) {
            $this->fail("Could not use PDO: " . $x->getMessage());
        }

        // CREATE DATABASE IF NOT EXISTS
        $this->pdo->exec(<<<SQL
            IF NOT EXISTS(SELECT name FROM sys.databases WHERE name = 'laravel')
            BEGIN
                CREATE DATABASE laravel;
            END
SQL);

        // sqlsrv works by specifying the database during connection
        $this->pdo = null;
        try {
            $this->pdo = new PDO("sqlsrv:server=(local);database=laravel", $dbUser, $dbPass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $x) {
            $this->fail("Could not use PDO: " . $x->getMessage());
        }

        // CREATE TABLE IF NOT EXISTS
        // create the table once; whatever happens, truncate the table to ensure clean starting state.
        $this->pdo->exec(<<<SQL
            IF NOT EXISTS(SELECT name FROM sys.tables WHERE name = 'cache')
            BEGIN
                CREATE TABLE IF NOT EXISTS cache (
                    `key`	VARCHAR(255) NOT NULL,
                    `value`	TEXT NOT NULL,
                    `expiration`	INTEGER NOT NULL,
                PRIMARY KEY(`key`));
            END
SQL);
        $this->pdo->exec("TRUNCATE TABLE cache");
        $this->pdo->exec(<<<SQL
            IF NOT EXISTS(SELECT name FROM sys.tables WHERE name = 'cache_locks')
            BEGIN
                CREATE TABLE IF NOT EXISTS cache_locks (
                    `key` VARCHAR(255) NOT NULL,
                    `owner` VARCHAR(255) NOT NULL,
                    `expiration` INTEGER NOT NULL,
                PRIMARY KEY (`key`))
            END
SQL);
        $this->pdo->exec("TRUNCATE TABLE cache_locks");

        Config::set('database.connections.sqlsrv.driver', 'sqlsrv');
        Config::set('database.connections.sqlsrv.host', 'localhost');
        Config::set('database.connections.sqlsrv.port', '1433');
        Config::set('database.connections.sqlsrv.database', 'laravel');
        Config::set('database.connections.sqlsrv.username', $dbUser);
        Config::set('database.connections.sqlsrv.password', $dbPass);
        Config::set('database.connections.sqlsrv.charset', 'utf8');

        // then, database cache
        $this->configureDatabaseCacheStoreForPrefix('database');
    }

    protected function tearDownCache(): void
    {
        // in our CI/CD use case it is not convenient to even drop the database.
        // so the approach is to ensure we have a clean table for testing.

        // we also reuse the connection so we are sure we are really in the laravel database
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
        Config::set("cache.stores.$cacheName.connection", 'sqlsrv');
        Config::set("cache.stores.$cacheName.lock_connection", '');
        if ($intendedPrefix !== null) {
            Config::set("cache.stores.$cacheName.prefix", $intendedPrefix);
        }
    }
}
