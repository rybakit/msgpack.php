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
    private const COLUMN_WIDTH_MIN = 9;
    private const COLUMN_WIDTH_FIRST = 20;

    private const STATUS_SKIPPED = 'S';
    private const STATUS_FAILED = 'F';
    private const STATUS_IGNORED = 'I';

    private $ignoreIncomplete;
    private $width;
    private $widths = [];

    private $total = [];
    private $skipped = [];
    private $failed = [];
    private $ignored = [];

    public function __construct(bool $ignoreIncomplete = null)
    {
        $this->ignoreIncomplete = $ignoreIncomplete ?? true;
    }

    public function open(array $benchmarkInfo, array $targets) : void
    {
        $this->widths = [self::COLUMN_WIDTH_FIRST];

        $cells = ['Test/Target'];
        foreach ($targets as $target) {
            $targetName = $target->getName();
            $this->widths[] = max(strlen($targetName) + 2, self::COLUMN_WIDTH_MIN);
            $cells[] = $targetName;

            $this->total[$targetName] = 0;
            $this->skipped[$targetName] = 0;
            $this->failed[$targetName] = 0;
            $this->ignored[$targetName] = 0;
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

    public function write(Test $test, array $stats) : void
    {
        $cells = [$test];
        $isIncomplete = false;

        foreach ($stats as $targetName => $result) {
            if (!$result instanceof \Exception) {
                $this->total[$targetName] += $result;
                $cells[$targetName] = sprintf('%.4f', $result);
                continue;
            }

            $isIncomplete = true;

            if ($result instanceof TestSkippedException) {
                ++$this->skipped[$targetName];
                $cells[$targetName] = self::STATUS_SKIPPED;
            } else {
                ++$this->failed[$targetName];
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
                $this->total[$targetName] -= $result;
                ++$this->ignored[$targetName];
            }
        }

        $this->writeRow($cells, '.');
    }

    public function close() : void
    {
        echo str_repeat('=', $this->width)."\n";

        $this->writeSummary('Total', $this->total, function ($value) {
            return sprintf('%.4f', $value);
        });

        $this->writeSummary('Skipped', $this->skipped);
        $this->writeSummary('Failed', $this->failed);
        $this->writeSummary('Ignored', $this->ignored);
    }

    private function writeSummary(string $title, array $values, \Closure $formatter = null) : void
    {
        $cells = [$title];

        foreach ($values as $value) {
            $cells[] = $formatter ? $formatter($value) : $value;
        }

        $this->writeRow($cells);
    }

    private function writeRow(array $cells, string $padChar = ' ') : void
    {
        $title = array_shift($cells);

        echo $title;
        $paddingLen = $this->widths[0] - strlen($title);

        if ($this->widths[0] > 1) {
            echo ' '.str_repeat($padChar, $paddingLen - 1);
        }

        $i = 1;
        foreach ($cells as $name => $value) {
            $multiplier = $this->widths[$i] - strlen($value) - 2;
            echo (1 === $i) ? $padChar : ' ';
            echo str_repeat($padChar, $multiplier > 0 ? $multiplier : 0).' ';
            echo $value;
            ++$i;
        }

        echo "\n";
    }
}
