<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Perf\Writer;

use MessagePack\Tests\Perf\Test;
use MessagePack\Tests\Perf\ValueAccumulator;

class TableWriter implements Writer
{
    const DEFAULT_WIDTH = 32;

    private $width;
    private $accumulator;

    public function __construct($width = null)
    {
        $this->width = $width ?: self:: DEFAULT_WIDTH;
        $this->accumulator = new ValueAccumulator();
    }

    public function open($target, array $benchmarkInfo)
    {
        $this->accumulator->set(0);

        echo "\nTarget: $target\n";
        foreach ($benchmarkInfo as $title => $value) {
            echo "$title: $value\n";
        }
        echo "\n";

        echo str_repeat('=', $this->width)."\n";

        printf("Test %s Time, sec\n", str_repeat(' ', $this->width - 15));
        echo str_repeat('-', $this->width)."\n";
    }

    public function writeResult(Test $test, $time)
    {
        $this->accumulator->add($time);

        $printTime = sprintf('%.4f', $time);

        printf("%s %s %s\n",
            $test,
            str_repeat('.', $this->width - strlen($test) - strlen($printTime) - 2),
            $printTime
        );
    }

    public function writeSkipped(Test $test)
    {
        printf("%s %s S\n", $test, str_repeat('.', $this->width - strlen($test) - 3));
    }

    public function writeFailed(Test $test, \Exception $e)
    {
        printf("%s %s F\n", $test, str_repeat('.', $this->width - strlen($test) - 3));
    }

    public function close()
    {
        $summary = sprintf('Total: %.4f', $this->accumulator->get());

        echo str_repeat('-', $this->width)."\n";
        echo str_repeat(' ', $this->width - strlen($summary)).$summary."\n\n";
    }
}
