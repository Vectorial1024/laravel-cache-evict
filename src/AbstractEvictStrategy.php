<?php

namespace Vectorial1024\LaravelCacheEvict;

abstract class AbstractEvictStrategy
{
    public function __construct(
        public readonly string $storeName
    ) {
    }

    /**
     * Execute the key eviction strategy.
     * 
     * Children classes should define their eviction logic here.
     */
    abstract public function execute();
}
