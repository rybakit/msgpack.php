<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Perf\Benchmark;

use MessagePack\Tests\Perf\Target\Target;
use MessagePack\Tests\Perf\Test;

class IterationBenchmark implements Benchmark
{
    private $iterations;

    public function __construct($iterations)
    {
        $this->iterations = $iterations;
    }

    public function benchmark(Target $target, Test $test)
    {
        $target->sanitize($test);

        $overheadTime = $this->measureOverhead($target, $test);
        $performTime = $this->measurePerform($target, $test);

        return $performTime - $overheadTime;
    }

    public function getInfo() : array
    {
        return ['Iterations' => $this->iterations];
    }

    private function measurePerform(Target $target, Test $test) : float
    {
        $time = microtime(true);

        for ($i = $this->iterations; $i; --$i) {
            $target->perform($test);
        }

        return microtime(true) - $time;
    }

    private function measureOverhead(Target $target, Test $test) : float
    {
        $time = microtime(true);

        for ($i = $this->iterations; $i; --$i) {
            $target->calibrate($test);
        }

        return microtime(true) - $time;
    }
}
