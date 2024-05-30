<?php

namespace Vectorial1024\LaravelCacheEvict\File;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Vectorial1024\LaravelCacheEvict\AbstractEvictStrategy;

class FileEvictStrategy extends AbstractEvictStrategy
{
    protected Filesystem $filesystem;

    public function __construct(string $storeName)
    {
        parent::__construct($storeName);    

        // read cache details
        $storeRoot = config("cache.stores.{$storeName}.path");

        // and then set the file system
        $this->filesystem = Storage::build([
            'driver' => 'local',
            'root' => $storeRoot,
        ]);
    }

    public function execute(): void
    {
        // read the cache config and set up targets

        // we use a memory-efficient way of deleting items.
        // we also use native functions if it makes sense
        // testing: print all the directories
        $allDirs = $this->filesystem->allDirectories();
        var_dump($allDirs);
    }
}
