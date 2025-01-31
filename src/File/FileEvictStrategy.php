<?php

namespace Vectorial1024\LaravelCacheEvict\File;

use DirectoryIterator;
use ErrorException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Helper\ProgressBar;
use Vectorial1024\LaravelCacheEvict\AbstractEvictStrategy;
use Wilderborn\Partyline\Facade as Partyline;

class FileEvictStrategy extends AbstractEvictStrategy
{
    protected Filesystem $filesystem;

    protected int $deletedFiles = 0;
    protected int $deletedFileSize = 0;
    protected int $deletedDirs = 0;

    protected float $elapsedTime = 0;

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
        $this->deletedFiles = 0;
        $this->deletedFileSize = 0;
        $this->deletedDirs = 0;
        $this->elapsedTime = 0;

        // we use a memory-efficient way of deleting items.
        // we also use native functions if it makes sense
        $startUnix = microtime(true);
        Partyline::info("Finding the cache directories...");
        $allDirs = $this->filesystem->allDirectories();
        Partyline::info("Found " . count($allDirs) . " cache directories to evict expired items; processing...");

        // create a progress bar to display our progress
        /** @var ProgressBar $progressBar */
        $progressBar = $this->output->createProgressBar();
        $progressBar->setMaxSteps(count($allDirs));

        // since allDir is an array with contents like "0", "0/0", "0/1", ... "1", ...
        // and we are trying to remove items during iteration
        // we should iterate it in reverse as per literation best practices
        // the reversal also cleanly avoids possible race conditions by aligning iteration direction across all cleaners
        foreach (array_reverse($allDirs) as $dir) {
            // handle cache files, then delete the directory in the same place
            $this->handleCacheFilesInDirectory($dir);
            $progressBar->advance();
            // it's OK if we cannot remove directories; this usually means the directory is not empty.
            $localPath = $this->filesystem->path($dir);
            @rmdir($localPath);
            $this->deletedDirs++;
        }

        $progressBar->finish();
        unset($progressBar);
        // progress bar next empty line
        Partyline::info("");
        Partyline::info("Expired cache files evicted.");

        // all is done; print some stats
        $endUnix = microtime(true);
        $this->elapsedTime = $endUnix - $startUnix;
        // generate a human readable file size
        $readableFileSize = $this->bytesToHuman($this->deletedFileSize);
        Partyline::info("Took {$this->elapsedTime} seconds.");
        Partyline::info("Removed {$this->deletedFiles} expired cache files. Estimated total size: $readableFileSize");
        Partyline::info("Removed {$this->deletedDirs} empty directories.");
    }

    protected function handleCacheFilesInDirectory(string $dirName)
    {
        $localPath = $this->filesystem->path($dirName);

        // remove files inside directory
        /** @var \SplFileInfo $fileInfo */
        foreach (new DirectoryIterator($localPath) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            if ($fileInfo->isDir()) {
                continue;
            }

            $realPath = $fileInfo->getRealPath();
            $shortFileName = $dirName . DIRECTORY_SEPARATOR . $fileInfo->getFilename();
            try {
                // read expiry
                // the first 10 characters form the expiry timestamp
                $size = $fileInfo->getSize();
                // no obvious performance improvement when using fopen; using file_get_contents for its simplicity 
                $expiry = (int) file_get_contents($realPath, length: 10);
                if (time() < $expiry) {
                    // not expired yet
                    continue;
                }
            } catch (ErrorException) {
                // it's OK if we cannot read the file, this can happen when e.g. the cache file is deleted by other Laravel code
                Partyline::warn("Could not read details of cache file $shortFileName; skipping.");
                continue;
            }

            try {
                // remove the file silently
                unlink($realPath);
                $this->deletedFileSize += $size;
                $this->deletedFiles++;
            } catch (ErrorException) {
                Partyline::warn("Could not delete cache file $shortFileName; skipping.");
            }
        }
    }
}
