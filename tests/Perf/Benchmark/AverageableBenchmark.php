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

class AverageableBenchmark implements Benchmark
{
    private $benchmark;
    private $rounds;

    public function __construct(Benchmark $benchmark, int $rounds = 3)
    {
        $this->benchmark = $benchmark;
        $this->rounds = $rounds;
    }

    public function benchmark(Target $target, Test $test)
    {
        $sum = 0;

        for ($i = $this->rounds; $i; --$i) {
            $sum += $this->benchmark->benchmark($target, $test);
        }

        return $sum / $this->rounds;
    }

    public function getInfo() : array
    {
        return ['Rounds' => $this->rounds] + $this->benchmark->getInfo();
    }
}
