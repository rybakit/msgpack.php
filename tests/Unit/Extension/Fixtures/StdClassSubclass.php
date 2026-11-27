<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\Extension\Fixtures;

class StdClassSubclass extends \stdClass
{
    public string $value = '';

    public function __serialize() : array
    {
        return ['value' => strtoupper($this->value)];
    }

    public function __unserialize(array $data) : void
    {
        $this->value = strtolower($data['value']);
    }
}
