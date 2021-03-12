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
    public $schema;
    public $map;

    public function __construct(array $schema, array $map)
    {
        $this->schema = $schema;
        $this->map = $map;
    }
}
