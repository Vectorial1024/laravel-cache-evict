<?php

namespace Vectorial1024\LaravelCacheEvict\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Vectorial1024\LaravelCacheEvict\AbstractEvictStrategy;

/**
 * A Laravel event that indicates a cache was just evicted of its expired items.
 */
class CacheEvictionCompleted
{
    use Dispatchable;

    /**
     * @param AbstractEvictStrategy $evictStrategy The eviction strategy that was completed.
     */
    public function __construct(
        public AbstractEvictStrategy $evictStrategy,
    ) {
    }
}
