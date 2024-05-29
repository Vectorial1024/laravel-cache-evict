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
        $this->info("Hello world!");
        return self::SUCCESS;
    }
}
