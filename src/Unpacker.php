<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack;

class Unpacker
{
    private $bufferUnpacker;

    public function __construct(BufferUnpacker $bufferUnpacker = null)
    {
        $this->bufferUnpacker = $bufferUnpacker ?: new BufferUnpacker();
    }

    public function unpack($data)
    {
        return $this->bufferUnpacker->reset($data)->unpack();
    }
}
