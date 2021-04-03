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
use MessagePack\Packer;
use MessagePack\TypeTransformer\Extension;

class StructListExtension implements Extension
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
        if (!$value instanceof StructList) {
            return null;
        }

        $size = \count($value->list);
        if ($size < 2) {
            return $packer->packArray($value->list);
        }

        $keys = \array_keys(\reset($value->list));

        $data = '';
        foreach ($value->list as $item) {
            foreach ($keys as $key) {
                $data .= $packer->pack($item[$key]);
            }
        }

        return $packer->packExt($this->type,
            $packer->packArray($keys).
            $packer->packArrayHeader($size).
            $data
        );
    }

    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        $keys = $unpacker->unpackArray();
        $size = $unpacker->unpackArrayHeader();

        $list = [];
        for ($i = 0; $i < $size; ++$i) {
            foreach ($keys as $key) {
                $list[$i][$key] = $unpacker->unpack();
            }
        }

        return $list;
    }
}
