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
    /**
     * @var Benchmark
     */
    private $benchmark;

    /**
     * @var int
     */
    private $cycles;

    public function __construct(Benchmark $benchmark, $cycles = null)
    {
        $this->benchmark = $benchmark;
        $this->cycles = $cycles ?: 3;
    }

    /**
     * {@inheritdoc}
     */
    public function benchmark(Target $target, Test $test)
    {
        $sum = 0;

        for ($i = $this->cycles; $i; $i--) {
            $sum += $this->benchmark->benchmark($target, $test);
        }

        return $sum / $this->cycles;
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo()
    {
        return ['Cycles' => $this->cycles] + $this->benchmark->getInfo();
    }
}
