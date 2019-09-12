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

class PausableBenchmark implements Benchmark
{
    private $benchmark;
    private $sleepMs;

    public function __construct(Benchmark $benchmark, int $sleepMs = 100)
    {
        $this->benchmark = $benchmark;
        $this->sleepMs = $sleepMs;
    }

    public function benchmark(Target $target, Test $test)
    {
        usleep($this->sleepMs * 1000);

        return $this->benchmark->benchmark($target, $test);
    }

    public function getInfo() : array
    {
        return $this->benchmark->getInfo();
    }
}
