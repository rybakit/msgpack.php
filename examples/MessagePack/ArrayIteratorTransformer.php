<?php

namespace App\MessagePack;

use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\TypeTransformer\Extension;

class ArrayIteratorTransformer implements Extension
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

    public function unpack(BufferUnpacker $unpacker, $extLength)
    {
        $size = $unpacker->unpackArrayHeader();

        while ($size--) {
            yield $unpacker->unpack();
        }
    }
}
