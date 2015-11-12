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

use MessagePack\Tests\Perf\Filter\Filter;
use MessagePack\Tests\Perf\Writer\TableWriter;
use MessagePack\Tests\Perf\Writer\Writer;

class Runner
{
    /**
     * @var array
     */
    private $data;

    /**
     * @var Writer
     */
    private $writer;

    /**
     * @var Filter[]
     */
    private $filters;

    public function __construct(array $data, Writer $writer = null)
    {
        $this->data = $data;
        $this->writer = $writer ?: new TableWriter();
    }

    public function addFilter(Filter $filter)
    {
        $this->filters[] = $filter;
    }

    public function run(Benchmark $benchmark)
    {
        $this->writer->init($benchmark->getTitle(), $benchmark->getSize());

        $totalTime = 0;

        foreach ($this->data as $set) {
            if ($this->isSkipped($set[0])) {
                $this->writer->addSkipped($set[0]);
                continue;
            }

            $totalTime += $time = $benchmark->measure($set[1], $set[2]);
            $this->writer->addMeasurement($set[0], $time);
        }

        $this->writer->finalize($totalTime);
    }

    private function isSkipped($test)
    {
        foreach ($this->filters as $filter) {
            if (!$filter->isAccepted($test)) {
                return true;
            }
        }

        return false;
    }
}
