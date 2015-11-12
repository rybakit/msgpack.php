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

class JsonWriter implements Writer
{
    private $data = [
        'tests' => [],
        'summary' => [],
    ];

    public function init($target, $size)
    {
        $this->data['summary'] = [
            'target' => $target,
            'size' => $size,
        ];
    }

    public function addSkipped($test)
    {
        $this->data['summary']['skipped'][] = $test;
    }

    public function addMeasurement($test, $time)
    {
        $this->data['tests'][$test] = $time;
    }

    public function finalize($totalTime)
    {
        $this->data['summary']['total'] = $totalTime;

        echo json_encode($this->data);
    }
}
