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

interface Writer
{
    public function open($target, array $info);
    public function writeResult(Test $test, $time);
    public function writeSkipped(Test $test);
    public function writeFailed(Test $test, \Exception $e);
    public function close();
}
