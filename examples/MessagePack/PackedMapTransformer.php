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

    public function getType()
    {
        return $this->type;
    }

    public function pack(Packer $packer, $value)
    {
        if (!$value instanceof PackedMap) {
            return null;
        }

        $data = '';
        $length = 0;
        foreach ($value->map as $item) {
            foreach ($value->schema as $key) {
                $data .= $packer->pack($item[$key]);
            }
            ++$length;
        }

        return $packer->packExt($this->type,
            $packer->packArray($value->schema).
            $packer->packArrayLength($length).
            $data
        );
    }

    public function unpack(BufferUnpacker $unpacker, $extLength)
    {
        $schema = $unpacker->unpackArray($unpacker->unpackArrayLength());

        $length = $unpacker->unpackArrayLength();
        $items = [];

        for ($i = 0; $i < $length; ++$i) {
            foreach ($schema as $key) {
                $items[$i][$key] = $unpacker->unpack();
            }
        }

        return $items;
    }
}
