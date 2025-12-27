<?php

namespace Vectorial1024\LaravelCacheEvict;

use Illuminate\Console\Command;
use Vectorial1024\LaravelCacheEvict\Events\CacheEvictionCompleted;

class CacheEvictCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:evict {target?}';

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
        // bind to partyline for easier output printing
        \Partyline::bind($this);

        // determine target cache to evict items
        $cacheTarget = $this->argument('target');
        if (!$cacheTarget) {
            $cacheTarget = config('cache.default');
            $this->info("No cache store target provided; targeting default store '{$cacheTarget}'");
        }

        // determine eviction eligibility/strategy
        $cacheConfig = config("cache.stores.{$cacheTarget}");
        if (!$cacheConfig) {
            $this->error("Cache store '{$cacheTarget}' does not exist; aborting.");
            return self::FAILURE;
        }
        $this->info("Reading details of cache store '{$cacheTarget}'...");
        // driver is basically required, but just in case
        $cacheDriver = config("cache.stores.{$cacheTarget}.driver");
        if (!$cacheDriver) {
            $this->error("Cache store '{$cacheTarget}' has no cache driver defined; aborting.");
            return self::FAILURE;
        }
        $this->info("The cache driver is '{$cacheDriver}'");
        try {
            $evictStrat = CacheEvictStrategies::getEvictionStrategy($cacheTarget, $cacheDriver);
            if ($evictStrat === null) {
                // we don't know what this is and therefore how to handle this
                $this->warn("Cache store '{$cacheTarget}' is using cache driver '{$cacheDriver}', but it does not have any corresponding eviction strategy. Perhaps the strategies are incomplete?");
                return self::INVALID;
            }
            $evictStrat->setOutput($this->output);
        } catch (EvictionRefusedFeatureExistsException) {
            $this->warn("Cache store '{$cacheTarget}' is using cache driver '{$cacheDriver}', but has its own eviction mechanisms; please check their documentations instead.");
            return self::INVALID;
        }

        // do the eviction
        $this->info("Evicting expired items...");
        $evictStrat->execute();

        // notify eviction complete
        $this->info("Eviction complete.");
        CacheEvictionCompleted::dispatch($evictStrat);
        return self::SUCCESS;
    }
}
