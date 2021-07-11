<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\MessagePack;

use MessagePack\Packer;
use MessagePack\TypeTransformer\CanBePacked;

final class Uint64 implements CanBePacked
{
    /** @readonly */
    public $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString() : string
    {
        return $this->value;
    }

    public function pack(Packer $packer) : string
    {
        return "\xcf".\gmp_export($this->value);
    }
}
