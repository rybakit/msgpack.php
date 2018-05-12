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
