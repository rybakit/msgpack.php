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

use MessagePack\Tests\Perf\Target\Target;
use MessagePack\Tests\Perf\Test;
use MessagePack\Tests\Perf\TestSkippedException;

class TableWriter implements Writer
{
    const FIRST_COLUMN_WIDTH = 20;

    const STATUS_SKIPPED = 'S';
    const STATUS_FAILED = 'F';
    const STATUS_IGNORED = 'I';

    private $ignoreIncomplete = true;
    private $width;
    private $widths = [];

    private $summary = [
        'total' => [],
        'skipped' => [],
        'failed' => [],
        'ingored' => [],
    ];

    public function __construct($ignoreIncomplete = null)
    {
        $this->ignoreIncomplete = null === $ignoreIncomplete ? true : $ignoreIncomplete;
    }

    /**
     * @param array $benchmarkInfo
     * @param Target[] $targets
     */
    public function open(array $benchmarkInfo, array $targets)
    {
        $this->widths = [self::FIRST_COLUMN_WIDTH];

        $cells = ['Test/Target'];
        foreach ($targets as $target) {
            $targetName = $target->getName();
            $this->widths[] = strlen($targetName) + 2;
            $cells[] = $targetName;

            $this->summary['total'][$targetName] = 0;
            $this->summary['skipped'][$targetName] = 0;
            $this->summary['failed'][$targetName] = 0;
            $this->summary['ignored'][$targetName] = 0;
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
        $isIncomplete = false;

        foreach ($stats as $targetName => $result) {
            if (!$result instanceof \Exception) {
                $this->summary['total'][$targetName] += $result;
                $cells[$targetName] = sprintf('%.4f', $result);
                continue;
            }

            $isIncomplete = true;

            if ($result instanceof TestSkippedException) {
                $this->summary['skipped'][$targetName]++;
                $cells[$targetName] = self::STATUS_SKIPPED;
            } else {
                $this->summary['failed'][$targetName]++;
                $cells[$targetName] = self::STATUS_FAILED;
            }
        }

        if ($this->ignoreIncomplete && $isIncomplete) {
            foreach ($stats as $targetName => $result) {
                $value = $cells[$targetName];

                if (self::STATUS_SKIPPED === $value || self::STATUS_FAILED === $value) {
                    continue;
                }

                $cells[$targetName] = self::STATUS_IGNORED;
                $this->summary['total'][$targetName] -= $result;
                $this->summary['ignored'][$targetName]++;
            }
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
        $this->writeSummary('Ignored', 'ignored');
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

        if ($this->widths[0] > 1) {
            echo ' '.str_repeat($padChar, $paddingLen - 1);
        }

        $i = 1;
        foreach ($cells as $name => $value) {
            echo (1 === $i) ? $padChar : ' ';
            echo str_repeat($padChar, $this->widths[$i] - strlen($value) - 2).' ';
            echo $value;
            $i++;
        }

        echo "\n";
    }
}
