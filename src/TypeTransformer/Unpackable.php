<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\TypeTransformer;

use MessagePack\BufferUnpacker;

interface Unpackable
{
    public function getType() : int;

    public function unpack(BufferUnpacker $unpacker, int $extLength);
}
