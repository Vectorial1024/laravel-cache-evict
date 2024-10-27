<?php

namespace Vectorial1024\LaravelCacheEvict\Test;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\EvictionRefusedFeatureExistsException;
use Vectorial1024\LaravelCacheEvict\File\FileEvictStrategy;

class CacheEvictStrategiesTest extends TestCase
{
    public function setUp(): void
    {
        CacheEvictStrategies::initOrReset();

        // set up cache configs
        // path does not matter at the moment
        // todo mark the directory correctly
        Config::set('cache.stores.file.path', 'asdf');
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
    }
}