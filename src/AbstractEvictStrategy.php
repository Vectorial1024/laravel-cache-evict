<?php

namespace Vectorial1024\LaravelCacheEvict;

use Illuminate\Console\OutputStyle;
use ramazancetinkaya\ByteFormatter;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

abstract class AbstractEvictStrategy
{
    protected OutputStyle $output;

    public function __construct(
        public readonly string $storeName
    ) {
        // default should be null output; useful when somehow calling this outside of Artisan console context
        // does not affect actual behavior
        // todo good opportunity to refactor with property hooks in PHP 8.4
        $this->output = new OutputStyle(new StringInput(''), new NullOutput());
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

    /**
     * Converts the given bytes to a human readable format, to be displayed to the user.
     * @param int $bytes
     * @return string The human-readable byte size.
     */
    protected function bytesToHuman(int $bytes): string
    {
        // in case the library broke, we can refer to this link: https://stackoverflow.com/questions/15188033/human-readable-file-size
        $formatter = new ByteFormatter();
        return $formatter->format($bytes);
    }
}
