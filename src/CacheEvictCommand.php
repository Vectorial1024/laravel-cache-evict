<?php

namespace Vectorial1024\LaravelCacheEvict;

use Illuminate\Console\Command;

class CacheEvictCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:evict';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Efficiently remove expired cache items';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // we will deal with the default cache for now
        $cacheTarget = null;
        if (!$cacheTarget) {
            $cacheTarget = config('cache.default');
            $this->info("No cache store provided; targeting default store '{$cacheTarget}'");
        }

        // determine eviction eligibility/strategy
        $cacheConfig = config("cache.stores.{$cacheTarget}");
        if (!$cacheConfig) {
            $this->error("Cache store '{$cacheTarget}' does not exist, could not read its details; aborting.");
            return self::FAILURE;
        }
        $this->info("Reading details of cache store '{$cacheTarget}'");
        // driver is basically required, but just in case
        $cacheDriver = config("cache.stores.{$cacheTarget}.driver");
        if (!$cacheDriver) {
            $this->error("Cache store '{$cacheTarget}' has no cache driver defined; aborting.");
            return self::FAILURE;
        }
        $this->info("The cache driver is '{$cacheDriver}'");
        try {
            $evictStrat = CacheEvictStrategies::getEvictionStrategy($cacheDriver);
            if ($evictStrat === null) {
                // we don't know what this is and therefore how to handle this
                $this->warn("Cache store '{$cacheTarget}' is using cache driver '{$cacheDriver}', but it does not have any corresponding eviction strategy. Perhaps the strategies are incomplete?");
                return self::INVALID;
            }
        } catch (EvictionRefusedFeatureExistsException) {
            $this->warn("Cache store '{$cacheTarget}' is using cache driver '{$cacheDriver}', but said driver already has its own key eviction strategies. Please refer to their documentation on how to evict keys.");
            return self::INVALID;
        }

        // do the eviction

        return self::SUCCESS;
    }
}
