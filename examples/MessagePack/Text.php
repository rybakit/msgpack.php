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
    public $str;

    public function __construct(string $str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}
