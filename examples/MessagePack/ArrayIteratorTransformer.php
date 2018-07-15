<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\MessagePack;

use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\TypeTransformer\Packable;
use MessagePack\TypeTransformer\Unpackable;

class ArrayIteratorTransformer implements Packable, Unpackable
{
    private $type;

    public function __construct(int $type)
    {
        $this->type = $type;
    }

    public function getType() : int
    {
        return $this->type;
    }

    public function pack(Packer $packer, $value) : ?string
    {
        if (!$value instanceof \ArrayIterator) {
            return null;
        }

        $data = '';
        $size = 0;
        foreach ($value as $item) {
            $data .= $packer->pack($item);
            ++$size;
        }

        return $packer->packExt($this->type,
            $packer->packArrayHeader($size).
            $data
        );
    }

    public function unpack(BufferUnpacker $unpacker, int $extLength)
    {
        $size = $unpacker->unpackArrayHeader();

        while ($size--) {
            yield $unpacker->unpack();
        }
    }
}
