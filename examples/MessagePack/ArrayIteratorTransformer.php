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
        $length = 0;
        foreach ($value as $item) {
            $data .= $packer->pack($item);
            ++$length;
        }

        return $packer->packExt($this->type,
            $packer->packArrayLength($length).
            $data
        );
    }

    public function unpack(BufferUnpacker $unpacker, $extLength)
    {
        $length = $unpacker->unpackArrayLength();

        while ($length--) {
            yield $unpacker->unpack();
        }
    }
}
