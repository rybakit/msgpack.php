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

class PrivateConstructorDto
{
    private function __construct(public string $value)
    {
    }

    public static function create(string $value) : self
    {
        return new self($value);
    }
}
