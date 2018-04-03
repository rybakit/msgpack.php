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

        $size = 0;
        $data = '';
        foreach ($value as $item) {
            $data .= $packer->pack($item);
            ++$size;
        }

        if ($size <= 0xf) {
            $data = \chr(0x90 | $size).$data;
        } elseif ($size <= 0xffff) {
            $data = "\xdc".\chr($size >> 8).\chr($size).$data;
        } else {
            $data = \pack('CN', 0xdd, $size).$data;
        }

        return $packer->packExt($this->type, $data);
    }

    public function unpack(BufferUnpacker $unpacker, $length)
    {
        $c = $unpacker->unpackUint8();

        if ($c >= 0x90 && $c <= 0x9f) {
            $size = $c & 0xf;
        } elseif (0xdc === $c) {
            $size = $unpacker->unpackUint16();
        } else {
            $size = $unpacker->unpackUint32();
        }

        while ($size--) {
            yield $unpacker->unpack();
        }
    }
}
