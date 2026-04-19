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

final class Text
{
    public function __construct(
        public readonly string $str,
    ) {
    }

    public function __toString() : string
    {
        return $this->str;
    }
}
