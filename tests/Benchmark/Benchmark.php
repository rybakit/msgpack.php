<?php

namespace MessagePack\Tests\Benchmark;

interface Benchmark
{
    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return int
     */
    public function getSize();

    /**
     * @param mixed  $raw
     * @param string $packed
     *
     * @return float
     */
    public function measure($raw, $packed);
}
