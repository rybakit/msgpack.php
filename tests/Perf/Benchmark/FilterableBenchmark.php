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

use MessagePack\Tests\Perf\Filter\Filter;
use MessagePack\Tests\Perf\Target\Target;
use MessagePack\Tests\Perf\Test;
use MessagePack\Tests\Perf\TestSkippedException;

class FilterableBenchmark implements Benchmark
{
    private $benchmark;
    private $filter;

    public function __construct(Benchmark $benchmark, Filter $filter)
    {
        $this->benchmark = $benchmark;
        $this->filter = $filter;
    }

    public function benchmark(Target $target, Test $test)
    {
        if (!$this->filter->isAccepted($test)) {
            throw new TestSkippedException($test);
        }

        return $this->benchmark->benchmark($target, $test);
    }

    public function getInfo() : array
    {
        return ['Filter' => get_class($this->filter)] + $this->benchmark->getInfo();
    }
}
