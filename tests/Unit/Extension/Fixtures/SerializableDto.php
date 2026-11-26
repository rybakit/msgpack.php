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

class SerializableDto
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
        public string $computed = '',
    ) {
    }

    public function __serialize() : array
    {
        return ['name' => $this->name, 'age' => $this->age];
    }

    public function __unserialize(array $data) : void
    {
        $this->name = $data['name'];
        $this->age = $data['age'];
        $this->computed = $this->name.' ('.$this->age.')';
    }
}
