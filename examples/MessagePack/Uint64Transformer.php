<?php

namespace App\MessagePack;

use MessagePack\Packer;
use MessagePack\TypeTransformer\Packable;

class Uint64Transformer implements Packable
{
    public function pack(Packer $packer, $value) : ?string
    {
        return $value instanceof Uint64
            ? "\xcf".\gmp_export($value->value)
            : null;
    }
}
