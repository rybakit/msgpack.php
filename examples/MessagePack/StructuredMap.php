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

final class StructuredMap
{
    public $items;
    public $schema;

    public function __construct(array $items, array $schema)
    {
        $this->items = $items;
        $this->schema = $schema;
    }
}
