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

class TraceableBenchmark implements Benchmark
{
    private $benchmark;
    private $trace;

    public function __construct(Benchmark $benchmark, callable $trace)
    {
        $this->benchmark = $benchmark;
        $this->trace = $trace;
    }

    public function benchmark(Target $target, Test $test)
    {
        $result = $this->benchmark->benchmark($target, $test);

        $trace = $this->trace;
        $trace($result, $test);

        return $result;
    }

    public function getInfo() : array
    {
        return $this->benchmark->getInfo();
    }
}
