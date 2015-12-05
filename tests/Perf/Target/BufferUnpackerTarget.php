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
    private $unpacker;

    public function __construct(BufferUnpacker $unpacker = null)
    {
        $this->unpacker = new BufferUnpacker();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return get_class($this->unpacker);
    }

    /**
     * {@inheritdoc}
     */
    public function sanitize(Test $test)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function perform(Test $test)
    {
        $this->unpacker->reset($test->getPacked());
        $this->unpacker->unpack();
    }

    /**
     * {@inheritdoc}
     */
    public function calibrate(Test $test)
    {
        $test->getPacked();
    }
}
