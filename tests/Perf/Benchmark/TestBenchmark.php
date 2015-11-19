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

class TestBenchmark implements Benchmark
{
    /**
     * @var int
     */
    private $iterations;

    public function __construct($iterations)
    {
        $this->iterations = $iterations;
    }

    public function benchmark(Target $target, Test $test)
    {
        $target->ensureSanity($test);

        for ($totalTime = 0, $i = $this->iterations; $i; $i--) {
            $totalTime += $target->measure($test);
        }

        return $totalTime;
    }

    public function getInfo()
    {
        return ['Iterations' => $this->iterations];
    }
}
