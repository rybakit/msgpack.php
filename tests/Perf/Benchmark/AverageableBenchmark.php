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
    private $rounds;

    public function __construct(Benchmark $benchmark, $rounds = null)
    {
        $this->benchmark = $benchmark;
        $this->rounds = $rounds ?: 3;
    }

    /**
     * {@inheritdoc}
     */
    public function benchmark(Target $target, Test $test)
    {
        $sum = 0;

        for ($i = $this->rounds; $i; --$i) {
            $sum += $this->benchmark->benchmark($target, $test);
        }

        return $sum / $this->rounds;
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo()
    {
        return ['Rounds' => $this->rounds] + $this->benchmark->getInfo();
    }
}
