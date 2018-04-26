<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack;

final class Ext
{
    public $type;
    public $data;

    public function __construct(int $type, string $data)
    {
        $this->type = $type;
        $this->data = $data;
    }
}
