<?php

namespace App\MessagePack;

use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\TypeTransformer\Extension;

class DateTimeTransformer implements Extension
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
        if (!$value instanceof \DateTimeInterface && !$value instanceof \DateTime) {
            return null;
        }

        $data = $packer->packStr($value->format(\DateTime::RFC3339));

        return $packer->packExt($this->type, $data);
    }

    public function unpack(BufferUnpacker $unpacker, $extLength)
    {
        $unpacker->skip(1);
        $data = $unpacker->unpackStr($extLength - 1);

        return new \DateTime($data);
    }
}
