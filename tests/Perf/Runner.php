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

    private $writer;

    public function __construct(array $testData, Writer $writer = null)
    {
        $this->testData = $testData;
        $this->writer = $writer ?: new TableWriter();
    }

    /**
     * @param Benchmark $benchmark
     * @param \MessagePack\Tests\Perf\Target\Target[] $targets
     *
     * @return array
     */
    public function run(Benchmark $benchmark, array $targets)
    {
        $result = [];

        foreach ($targets as $target) {
            $result[$target->getName()] = $this->runTarget($benchmark, $target);
        }

        return $result;
    }

    private function runTarget(Benchmark $benchmark, Target $target)
    {
        $this->writer->open($target->getName(), $benchmark->getInfo());

        $stats = [];

        foreach ($this->testData as $row) {
            $test = new Test($row[0], $row[1], $row[2]);

            try {
                $stats[$test->getName()] = $time = $benchmark->benchmark($target, $test);
                $this->writer->writeResult($test, $time);
            } catch (TestSkippedException $e) {
                $this->writer->writeSkipped($test);
            } catch (\Exception $e) {
                $this->writer->writeFailed($test, $e);
            }
        }

        $this->writer->close();

        return $stats;
    }
}
