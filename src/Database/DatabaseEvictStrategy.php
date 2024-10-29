<?php

namespace Vectorial1024\LaravelCacheEvict\Database;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;
use Vectorial1024\LaravelCacheEvict\AbstractEvictStrategy;
use Wilderborn\Partyline\Facade as Partyline;

class DatabaseEvictStrategy extends AbstractEvictStrategy
{
    protected Connection $dbConn;

    protected string $dbTable;

    protected Repository $cacheStore;

    protected int $deletedRecords = 0;
    protected int $deletedRecordSizes = 0;

    protected float $elapsedTime = 0;

    public function __construct(string $storeName)
    {
        parent::__construct($storeName);    

        // read cache details, and set the specs
        $storeConn = config("cache.stores.{$storeName}.connection");
        $this->dbConn = DB::connection($storeConn);
        $this->dbTable = config("cache.stores.{$storeName}.table");
        $this->cacheStore = Cache::store($this->storeName);
    }

    public function execute(): void
    {
        // read the cache config and set up targets
        $this->deletedRecords = 0;
        $this->deletedRecordSizes = 0;
        $this->deletedDirs = 0;
        $this->elapsedTime = 0;

        // we use a memory-efficient way of deleting items.
        $startUnix = microtime(true);
        // we cannot do a full table scan since this might lock the table for too long, so we will find items iteratively.
        // the key field is indexed, so it is not too bad
        Partyline::info("Finding relevant cache records...");
        // cache might have prefix!
        $cachePrefix = $this->cacheStore->getPrefix();
        $itemCount = $this->dbConn
            ->table($this->dbTable)
            ->where('key', 'LIKE', "$cachePrefix%")
            ->count();
        Partyline::info("Found $itemCount records; processing...");
        
        // create a progress bar to display our progress
        /** @var ProgressBar $progressBar */
        $progressBar = $this->output->createProgressBar();
        $progressBar->setMaxSteps($itemCount);
        foreach ($this->yieldCacheTableItems() as $cacheItem) {
            // read record details
            $currentUserKey = $cacheItem->key;
            // currently timestamps are 32-bit, so are 4 bytes
            $estimatedBytes = $cacheItem->key_bytes + $cacheItem->value_bytes + 4;
            $progressBar->advance();

            // then, use the cache method to attempt to load it
            // this respects potential db cache locks that the cache store might have set
            // the cache function will help us forget the item, so if the returned value is null, then we are sure the thing is expired
            $cachedValue = $this->cacheStore->get($currentUserKey);
            if ($cachedValue === null) {
                // item likely expired
                $this->deletedRecords += 1;
                $this->deletedRecordSizes += $estimatedBytes;
            }
        }

        // report results:
        // progress bar next empty line
        Partyline::info("");

        // all is done; print some stats
        $endUnix = microtime(true);
        $this->elapsedTime = $endUnix - $startUnix;
        // generate a human readable file size
        $readableFileSize = $this->bytesToHuman($this->deletedRecordSizes);
        Partyline::info("Took {$this->elapsedTime} seconds.");
        Partyline::info("Removed {$this->deletedRecords} expired cache records. Estimated total size: $readableFileSize");
        Partyline::info("Note: no free space reclaimed; reclaiming free space should be done manually!");
    }

    protected function yieldCacheTableItems(): \Generator
    {
        // there might be a prefix for the cache store!
        // not sure how to properly type-cast to DatabaseStore, but this should exist.
        $cachePrefix = $this->cacheStore->getPrefix();
        $currentUserKey = "";
        // loop until no more items
        while (true) {
            // find the next key
            $actualKey = "{$cachePrefix}{$currentUserKey}";
            // Partyline::info("Checking DB key $actualKey");
            $record = $this->dbConn
                ->table($this->dbTable)
                ->select(['key', DB::raw('LENGTH(key) AS key_bytes'), DB::raw('LENGTH(value) AS value_bytes')])
                ->where('key', '>', $actualKey)
                ->where('key', 'LIKE', "$cachePrefix%")
                ->limit(1)
                ->first();
            // Partyline::info(var_dump($record));
            if (!$record) {
                // nothing more to get
                break;
            }

            yield $record;
            $currentUserKey = $record->key;
        }
        // loop exit handled inside while loop
    }
}
