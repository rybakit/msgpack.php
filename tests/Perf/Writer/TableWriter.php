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
use MessagePack\Tests\Perf\TestSkippedException;

class TableWriter implements Writer
{
    const FIRST_COLUMN_WIDTH = 20;

    private $width;
    private $widths = [];
    private $summary = [
        'total' => [],
        'skipped' => [],
        'failed' => [],
    ];

    public function open(array $benchmarkInfo, array $targets)
    {
        $this->widths = [self::FIRST_COLUMN_WIDTH];

        $cells = ['Test/Target'];
        foreach ($targets as $target) {
            $targetName = $target->getName();
            $this->widths[] = strlen($targetName) + 1;
            $cells[] = $targetName;

            $this->summary['total'][$targetName] = 0;
            $this->summary['skipped'][$targetName] = 0;
            $this->summary['failed'][$targetName] = 0;
        }

        $this->width = array_sum($this->widths);

        foreach ($benchmarkInfo as $title => $value) {
            echo "$title: $value\n";
        }
        echo "\n";

        echo str_repeat('=', $this->width)."\n";
        $this->writeRow($cells);
        echo str_repeat('-', $this->width)."\n";
    }

    public function write(Test $test, array $stats)
    {
        $cells = [$test];

        foreach ($stats as $targetName => $result) {
            if ($result instanceof \Exception) {
                if ($result instanceof TestSkippedException) {
                    $this->summary['skipped'][$targetName]++;
                    $cells[] = 'S';
                } else {
                    $this->summary['failed'][$targetName]++;
                    $cells[] = 'F';
                }

                continue;
            }

            $this->summary['total'][$targetName] += $result;

            $cells[] = sprintf('%.4f', $result);
        }

        $this->writeRow($cells, '.');
    }

    public function close()
    {
        echo str_repeat('=', $this->width)."\n";

        $this->writeSummary('Total', 'total', function ($value) {
            return sprintf('%.4f', $value);
        });

        $this->writeSummary('Skipped', 'skipped');
        $this->writeSummary('Failed', 'failed');
    }

    private function writeSummary($title, $name, \Closure $formatter = null)
    {
        $cells = [$title];

        foreach ($this->summary[$name] as $value) {
            $cells[] = $formatter ? $formatter($value) : $value;
        }

        $this->writeRow($cells);
    }

    private function writeRow(array $cells, $padChar = ' ')
    {
        $title = array_shift($cells);

        echo $title;
        $paddingLen = $this->widths[0] - strlen($title);

        if ($this->widths[0] > 0) {
            echo str_repeat($padChar, $paddingLen);
        }

        $i = 1;
        foreach ($cells as $name => $value) {
            echo str_repeat($padChar, $this->widths[$i] - strlen($value) - 1).' ';
            echo $value;
            $i++;
        }

        echo "\n";
    }
}
