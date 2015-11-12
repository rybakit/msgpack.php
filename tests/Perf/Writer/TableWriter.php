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

class TableWriter implements Writer
{
    const DEFAULT_WIDTH = 34;

    private $width;

    public function __construct($width = null)
    {
        $this->width = $width ?: self:: DEFAULT_WIDTH;
    }

    public function init($target, $size)
    {
        echo str_repeat('=', $this->width)."\n";
        printf("Target: %s\n", $target);
        printf("Size: %s\n", $size);
        echo str_repeat('=', $this->width)."\n";

        printf("Test %s Time, sec\n", str_repeat(' ', $this->width - 15));
        echo str_repeat('-', $this->width)."\n";
    }

    public function addSkipped($test)
    {
        printf("%s %s S\n", $test, str_repeat('.', $this->width - strlen($test) - 3));
    }

    public function addMeasurement($test, $time)
    {
        $printTime = sprintf('%.4f', $time);

        printf("%s %s %s\n",
            $test,
            str_repeat('.', $this->width - strlen($test) - strlen($printTime) - 2),
            $printTime
        );
    }

    public function finalize($totalTime)
    {
        $summary = sprintf('Total: %.4f', $totalTime);

        echo str_repeat('-', $this->width)."\n";
        echo str_repeat(' ', $this->width - strlen($summary)).$summary."\n\n";
    }
}
