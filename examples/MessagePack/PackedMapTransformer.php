<?php

namespace App\MessagePack;

use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\TypeTransformer\Extension;

class PackedMapTransformer implements Extension
{
    private $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    public function getType() : int
    {
        return $this->type;
    }

    public function pack(Packer $packer, $value) : ?string
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

        return $packer->packExt($this->type,
            $packer->packMap($value->schema).
            $packer->packArrayHeader($size).
            $data
        );
    }

    public function unpack(BufferUnpacker $unpacker, int $extLength)
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
