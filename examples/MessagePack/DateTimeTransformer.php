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

        return $packer->packExt($this->type,
            $packer->packStr($value->format(\DateTime::RFC3339))
        );
    }

    public function unpack(BufferUnpacker $unpacker, $extLength)
    {
        return new \DateTime($unpacker->unpackStr());
    }
}
