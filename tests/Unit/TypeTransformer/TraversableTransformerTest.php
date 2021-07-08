<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\TypeTransformer;

use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\TypeTransformer\TraversableTransformer;
use PHPUnit\Framework\TestCase;

class TraversableTransformerTest extends TestCase
{
    public function testPackTraversableToMap() : void
    {
        $value = new \ArrayIterator([
            'a' => 1,
            'b' => new \ArrayIterator([
                'd' => 2,
                'e' => new \ArrayIterator(['g' => 3]),
                'f' => 4,
            ]),
            'c' => 5,
        ]);

        $transformer = TraversableTransformer::toMap();
        $packer = new Packer(null, [$transformer]);

        $packed = $packer->pack($value);
        $unpacked = (new BufferUnpacker($packed))->unpack();

        self::assertSame([
            'a' => 1,
            'b' => [
                'd' => 2,
                'e' => ['g' => 3],
                'f' => 4,
            ],
            'c' => 5,
        ], $unpacked);
    }

    public function testPackTraversableToArray() : void
    {
        $value = new \ArrayIterator([
            'a' => 1,
            'b' => new \ArrayIterator([
                'd' => 2,
                'e' => new \ArrayIterator(['g' => 3]),
                'f' => 4,
            ]),
            'c' => 5,
        ]);

        $transformer = TraversableTransformer::toArray();
        $packer = new Packer(null, [$transformer]);

        $packed = $packer->pack($value);
        $unpacked = (new BufferUnpacker($packed))->unpack();

        self::assertSame([1, [2, [3], 4], 5], $unpacked);
    }

    public function testPackNonTraversable() : void
    {
        $transformer = TraversableTransformer::toMap();
        $packer = new Packer(null, [$transformer]);

        self::assertNull($transformer->pack($packer, 'foobar'));
    }
}
