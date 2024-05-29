<?php

namespace Vectorial1024\LaravelCacheEvict;

use Exception;

class EvictionRefusedFeatureExistsException extends Exception
{
    public function __construct()
    {
        parent::__construct("Key eviction refused because the same feature already exists in the cache driver itself");
    }
}
