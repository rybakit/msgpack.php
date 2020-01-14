<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\TypeTransformer;

use MessagePack\Packer;
use MessagePack\Type\Bin;

class BinTransformer implements CanPack
{
    public function pack(Packer $packer, $value) : ?string
    {
        return $value instanceof Bin
            ? $packer->packBin($value->data)
            : null;
    }
}
