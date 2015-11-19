<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Perf;

class ValueAccumulator
{
    private $value;

    public function add($value)
    {
        $this->value += $value;
    }

    public function set($value)
    {
        $this->value = $value;
    }

    public function get()
    {
        return $this->value;
    }
}
