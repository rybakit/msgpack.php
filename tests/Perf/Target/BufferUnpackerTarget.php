<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Perf\Target;

use MessagePack\BufferUnpacker;
use MessagePack\Tests\Perf\Test;

class BufferUnpackerTarget implements Target
{
    private $name;
    private $bufferUnpacker;

    public function __construct(string $name = null, BufferUnpacker $bufferUnpacker = null)
    {
        $this->bufferUnpacker = $bufferUnpacker ?: new BufferUnpacker();
        $this->name = $name ?: get_class($this->bufferUnpacker);
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function sanitize(Test $test) : void
    {
    }

    public function perform(Test $test) : void
    {
        $this->bufferUnpacker->reset($test->getPacked());
        $this->bufferUnpacker->unpack();
    }

    public function calibrate(Test $test) : void
    {
        $test->getPacked();
    }
}
