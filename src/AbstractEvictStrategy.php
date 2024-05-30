<?php

namespace Vectorial1024\LaravelCacheEvict;

use Illuminate\Console\OutputStyle;

abstract class AbstractEvictStrategy
{
    protected OutputStyle $output;

    public function __construct(
        public readonly string $storeName
    ) {
    }

    /**
     * Sets the console output of this strategy; this may be helpful when creating progress bars.
     */
    public function setOutput(OutputStyle $output)
    {
        $this->output = $output;
    }

    /**
     * Execute the key eviction strategy.
     * 
     * Children classes should define their eviction logic here.
     */
    abstract public function execute();
}
