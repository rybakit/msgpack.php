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
use MessagePack\TypeTransformer\Extension;

class PackedMapExtension extends Extension
{
    protected function packExt(Packer $packer, $value) : ?string
    {
        if (!$value instanceof PackedMap) {
            return null;
        }

        $data = '';
        $size = 0;
        foreach ($value->map as $item) {
            foreach ($value->schema as $key => $type) {
                $data .= $packer->{'pack'.$type}($item[$key]);
            }
            ++$size;
        }

        return $packer->packMap($value->schema)
            .$packer->packArrayHeader($size)
            .$data;
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
