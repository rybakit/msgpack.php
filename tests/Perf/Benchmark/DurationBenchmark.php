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

class DurationBenchmark implements Benchmark
{
    private $duration;

    public function __construct($duration)
    {
        $this->duration = $duration;
    }

    /**
     * {@inheritdoc}
     */
    public function benchmark(Target $target, Test $test)
    {
        $target->sanitize($test);

        $iterations = $this->measurePerform($target, $test);
        $overheadTime = $this->measureOverhead($target, $test, $iterations);

        $extraIterations = round($overheadTime * $iterations / $this->duration);

        return $iterations + $extraIterations;
    }

    public function getInfo() : array
    {
        return ['Duration' => $this->duration];
    }

    private function measurePerform(Target $target, Test $test) : int
    {
        $iterations = 0;
        $time = microtime(true) + $this->duration;

        while (microtime(true) <= $time) {
            $target->perform($test);
            ++$iterations;
        }

        return $iterations;
    }

    private function measureOverhead(Target $target, Test $test, $iterations) : float
    {
        $time = microtime(true);

        for ($i = $iterations; $i; --$i) {
            $target->calibrate($test);
        }

        return microtime(true) - $time;
    }
}
