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

use MessagePack\Tests\Perf\Test;
use MessagePack\Tests\Perf\Target\Target;

class TimeBenchmark implements Benchmark
{
    /**
     * @var float
     */
    private $totalTime;

    public function __construct($totalTime)
    {
        $this->totalTime = $totalTime;
    }

    /**
     * {@inheritdoc}
     */
    public function benchmark(Target $target, Test $test)
    {
        $target->sanitize($test);

        $iterations = $this->measurePerform($target, $test);
        $overheadTime = $this->measureOverhead($target, $test, $iterations);

        $extraIterations = round($overheadTime * $iterations / $this->totalTime);

        return $iterations + $extraIterations;
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo()
    {
        return ['Time per test' => $this->totalTime];
    }

    private function measurePerform(Target $target, Test $test)
    {
        $iterations = 0;
        $maxTime = microtime(true) + $this->totalTime;

        while (microtime(true) <= $maxTime) {
            $target->perform($test);
            ++$iterations;
        }

        return $iterations;
    }

    private function measureOverhead(Target $target, Test $test, $iterations)
    {
        $time = microtime(true);

        for ($i = $iterations; $i; $i--) {
            $target->calibrate($test);
        }

        return microtime(true) - $time;
    }
}
