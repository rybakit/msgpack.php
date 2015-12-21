<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Perf;

use MessagePack\Tests\Perf\Benchmark\Benchmark;
use MessagePack\Tests\Perf\Target\Target;
use MessagePack\Tests\Perf\Writer\TableWriter;
use MessagePack\Tests\Perf\Writer\Writer;

class Runner
{
    /**
     * @var array
     */
    private $testData;

    /**
     * @var Writer
     */
    private $writer;

    public function __construct(array $testData, Writer $writer = null)
    {
        $this->testData = $testData;
        $this->writer = $writer ?: new TableWriter();
    }

    /**
     * @param Benchmark $benchmark
     * @param Target[] $targets
     *
     * @return array
     */
    public function run(Benchmark $benchmark, array $targets)
    {
        $this->writer->open($benchmark->getInfo(), $targets);

        $result = [];
        foreach ($this->testData as $row) {
            $test = new Test($row[0], $row[1], $row[2]);

            $stats = [];
            foreach ($targets as $target) {
                try {
                    $stats[$target->getName()] = $benchmark->benchmark($target, $test);
                } catch (\Exception $e) {
                    $stats[$target->getName()] = $e;
                }
            }

            $result[$test->getName()] = $stats;
            $this->writer->write($test, $stats);
        }

        $this->writer->close();

        return $result;
    }
}
