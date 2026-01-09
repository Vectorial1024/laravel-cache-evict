<?php

namespace Vectorial1024\LaravelCacheEvict\Test\File;

use Illuminate\Support\Facades\Config;
use Vectorial1024\LaravelCacheEvict\CacheEvictStrategies;
use Vectorial1024\LaravelCacheEvict\Test\Core\AbstractCacheEvictTestCase;

class FileEvictTest extends AbstractCacheEvictTestCase
{
    protected function setUpCache(): void
    {
        // configure the cache directory
        $projectRoot = $this->getProjectRoot();

        $fileCacheDir = "$projectRoot/storage/framework/cache/data";
        Config::set('cache.stores.file.driver', 'file');
        Config::set('cache.stores.file.path', $fileCacheDir);
        Config::set('cache.stores.file.lock_path', $fileCacheDir);
    }

    protected function tearDownCache(): void
    {
        // we should remove the files in the cache directory, but it seems it is safe to not do it
    }

    protected function getStoreName(): string
    {
        return "file";
    }

    protected function getCacheDriverName(): string
    {
        return CacheEvictStrategies::DRIVER_FILE;
    }
}
