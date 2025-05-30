<?php

declare(strict_types=1);

namespace Vectorial1024\LaravelCacheEvict\Database;

use Illuminate\Cache\DatabaseStore;
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

    protected DatabaseStore $cacheStore;

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
        $this->cacheStore = Cache::store($this->storeName)->getStore();
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
            $currentExpiration = $cacheItem->expiration;
            $currentActualKey = "{$cachePrefix}{$currentUserKey}";
            // currently timestamps are 32-bit, so are 4 bytes
            $estimatedBytes = (int) ($cacheItem->key_bytes + $cacheItem->value_bytes + 4);
            $progressBar->advance();

            if (time() < $currentExpiration) {
                // not expired yet
                continue;
            }
            // item expired; try to issue a delete command to it
            // this respects any potential new value written to the db while we were checking other things
            $rowsAffected = $this->dbConn
                ->table($this->dbTable)
                ->where('key', '=', $currentActualKey)
                ->where('expiration', '=', $currentExpiration)
                ->delete();
            if ($rowsAffected) {
                // item really expired with no new values
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
        $cachePrefix = $this->cacheStore->getPrefix();
        $currentUserKey = "";
        // loop until no more items
        while (true) {
            // find the next key
            $actualKey = "{$cachePrefix}{$currentUserKey}";
            $record = $this->dbConn
                ->table($this->dbTable)
                ->select(['key', 'expiration', DB::raw('LENGTH(key) AS key_bytes'), DB::raw('LENGTH(value) AS value_bytes')])
                ->where('key', '>', $actualKey)
                ->where('key', 'LIKE', "$cachePrefix%")
                ->limit(1)
                ->first();
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
