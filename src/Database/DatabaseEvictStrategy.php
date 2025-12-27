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
    /**
     * @var int
     * @deprecated Because this tool does not actually free table spaces, there is no need to track the amount of space freed from eviction.
     */
    protected int $deletedRecordSizes = 0;

    protected float $elapsedTime = 0;

    protected string $cachePrefix = "";

    public function __construct(string $storeName)
    {
        parent::__construct($storeName);

        // read cache details, and set the specs
        $storeConn = config("cache.stores.{$storeName}.connection");
        $this->dbConn = DB::connection($storeConn);
        $this->dbTable = config("cache.stores.{$storeName}.table");
        $this->cacheStore = Cache::store($this->storeName)->getStore();

        // write down the cache prefix; cache might have that
        // currently Laravel's cache prefix is only applied very easily by "{prefix}{key}"
        $this->cachePrefix = $this->cacheStore->getPrefix();
    }

    /**
     * Returns the number of records deleted in this eviction.
     * @return int
     */
    public function getDeletedRecords(): int
    {
        return $this->deletedRecords;
    }

    /**
     * Returns the total time elapsed (in seconds) during this eviction.
     * @return float
     */
    public function getElapsedTime(): float
    {
        return $this->elapsedTime;
    }

    public function execute(): void
    {
        // read the cache config and set up targets
        $this->deletedRecords = 0;
        $this->elapsedTime = 0;

        // we use a memory-efficient way of deleting items.
        $startUnix = microtime(true);
        // we cannot do a full table scan since this might lock the table for too long, so we will find items iteratively.
        // the key field is indexed, so it is not too bad
        Partyline::info("Finding relevant cache records...");
        // cache might have prefix!
        $itemCount = $this->dbConn
            ->table($this->dbTable)
            ->where('key', 'LIKE', "{$this->cachePrefix}%")
            ->count();
        Partyline::info("Found $itemCount records; processing...");
        
        // create a progress bar to display our progress
        $progressBar = $this->output->createProgressBar($itemCount);
        foreach ($this->yieldCacheTableChunks() as $chunk) {
            $progressBar->advance(count($chunk));

            // identify the keys of the records that are potentially expired
            $currentTimestamp = time();
            $possibleExpiredKeys = [];
            foreach ($chunk as $cacheItem) {
                $currentActualKey = $cacheItem->key;
                $currentExpiration = $cacheItem->expiration;
                if ($currentTimestamp < $currentExpiration) {
                    // not expired yet
                    continue;
                }
                // item expired; put to the deletion queue
                $possibleExpiredKeys[] = $currentActualKey;
            }

            // try to issue a delete command to the database
            // this respects any potential new value written to the db while we were checking other things
            $rowsAffected = $this->dbConn
                ->table($this->dbTable)
                ->whereIn('key', $possibleExpiredKeys)
                ->where('expiration', '<=', $currentTimestamp)
                ->delete();
            if ($rowsAffected) {
                // items really expired with no new updates
                $this->deletedRecords += $rowsAffected;
            }

            // reduce stampeding
            usleep(1000);
        }

        // report results:
        // progress bar next empty line
        Partyline::info("");

        // all is done; print some stats
        $endUnix = microtime(true);
        $this->elapsedTime = $endUnix - $startUnix;
        // note: the database evictor does not help reclaim free table spaces, so no need to print file size information
        Partyline::info("Took {$this->elapsedTime} seconds.");
        Partyline::info("Removed {$this->deletedRecords} expired cache records.");
        Partyline::info("Note: no free space reclaimed; reclaiming free space should be done manually!");
    }

    /**
     * Yields the next item from the cache table that belongs to this cache.
     * 
     * This method will return the actual key (with the cache prefix if exists) of the entry.
     * @deprecated This method is deprecated in favor of chunked deletion. Currently, it fetches and yields nothing.
     * @return \Generator<mixed, object, mixed, void>
     */
    protected function yieldCacheTableItems(): \Generator
    {
        yield new \stdClass();
    }

    /**
     * Yields the next chunk of many items from the cache table that belongs to this cache.
     *
     * This method will return the actual keys (with the cache prefix if exists) of the entries.
     * @return \Generator<mixed, array, mixed, void>
     */
    protected function yieldCacheTableChunks(): \Generator
    {
        // there might be a prefix for the cache store!
        $cachePrefix = $this->cachePrefix;
        // initialize the key to be just the cache prefix as the "zero string".
        $currentActualKey = $cachePrefix;
        $prefixLength = strlen($cachePrefix);
        // the cache table uses (MySQL) utf8mb4 collation (4 bytes) for its key column with max length 256
        // we estimate this should result in max allocation of about $chunkCount * 4 * 256 bytes throughout the eviction
        // remember to avoid excessive chunk sizes so that full-table locking is less likely to occur
        $chunkCount = 100;
        // loop until no more items
        while (true) {
            // find the next key
            // note: different SQL flavors have different interpretations of LIKE, so we use SUBSTRING instead.
            // with SUBSTRING, we are clear we want a case-sensitive match, and we might potentially get collation-correct matching
            $recordsList = $this->dbConn
                ->table($this->dbTable)
                ->select(['key', 'expiration'])
                ->where('key', '>', $currentActualKey)
                ->where(DB::raw("SUBSTRING(`key`, 1, $prefixLength)"), '=', $cachePrefix)
                // PostgreSQL: if no sorting specified, then will ignore primary key index/ordering, which breaks the intended workflow
                ->orderBy('key')
                ->limit($chunkCount)
                ->get();
            if ($recordsList->isEmpty()) {
                // nothing more to get
                break;
            }

            $theChunk = $recordsList->all();
            yield $theChunk;
            // find the last element, and set it to be the current key to continue table-walking
            $lastItem = end($theChunk);
            $currentActualKey = $lastItem->key;
        }
        // loop exit handled inside while loop
    }
}
