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

class StructuredMapExtension implements Extension
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
        if (!$value instanceof StructuredMap) {
            return null;
        }

        $size = \count($value->items);
        if ($size < 2) {
            return $packer->packArray($value->items);
        }

        $data = '';
        foreach ($value->items as $item) {
            foreach ($value->schema as $key => $type) {
                $data .= $packer->{'pack'.$type}($item[$key]);
            }
        }

        return $packer->packExt($this->type,
            $packer->packMap($value->schema).
            $packer->packArrayHeader($size).
            $data
        );
    }

    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        $schema = $unpacker->unpackMap();
        $size = $unpacker->unpackArrayHeader();

        $items = [];
        for ($i = 0; $i < $size; ++$i) {
            foreach ($schema as $key => $type) {
                $items[$i][$key] = $unpacker->{'unpack'.$type}();
            }
        }

        return $items;
    }
}
