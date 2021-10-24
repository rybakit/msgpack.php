<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\MessagePack;

use MessagePack\BufferUnpacker;
use MessagePack\Extension;
use MessagePack\Packer;

class TraversableExtension implements Extension
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
        if (!$value instanceof \Traversable) {
            return null;
        }

        $data = '';
        $size = 0;
        foreach ($value as $item) {
            $data .= $packer->pack($item);
            ++$size;
        }

        return $packer->packExt($this->type,
            $packer->packArrayHeader($size).$data
        );
    }

    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        $size = $unpacker->unpackArrayHeader();

        while ($size--) {
            yield $unpacker->unpack();
        }
    }
}
