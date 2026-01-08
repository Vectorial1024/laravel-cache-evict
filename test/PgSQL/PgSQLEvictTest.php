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

        // in our CI/CD use case it is not convenient to even drop the database.
        // so the approach is to ensure we have a clean table for testing.

        // create database if not exists <name>
        $this->pdo->exec("CREATE DATABASE laravel");
        $this->pdo->exec(<<<SQL
            SELECT 'CREATE DATABASE laravel'
                WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'laravel')
SQL);

        // postgresql works by specifying the database during connection
        $this->pdo = null;
        try {
            $this->pdo = new PDO("pgsql:host=$dbHost;dbname=laravel", $dbUser, $dbPass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $x) {
            $this->fail("Could not use PDO: " . $x->getMessage());
        }

        // create the table once; whatever happens, truncate the table to ensure clean starting state.
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS cache (
                "key"	VARCHAR(255) NOT NULL,
                value	TEXT NOT NULL,
                expiration	INTEGER NOT NULL,
            PRIMARY KEY("key"))
SQL);
        $this->pdo->exec("TRUNCATE cache");
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS cache_locks (
                "key" VARCHAR(255) NOT NULL,
                owner VARCHAR(255) NOT NULL,
                expiration INTEGER NOT NULL,
            PRIMARY KEY ("key"))
SQL);
        $this->pdo->exec("TRUNCATE cache_locks");

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
        // in our CI/CD use case it is not convenient to even drop the database.
        // so the approach is to ensure we have a clean table for testing.

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
