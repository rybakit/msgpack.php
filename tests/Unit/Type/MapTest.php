<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\Type;

use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\Type\Map;
use PHPUnit\Framework\TestCase;

final class MapTest extends TestCase
{
    public function testConstructor() : void
    {
        $raw = [1, 2, 3];
        $map = new Map($raw);

        self::assertSame($raw, $map->map);
    }

    public function testCanBePacked() : void
    {
        $raw = [1, 2, 3];
        $packer = new Packer();

        $packed = $packer->pack(new Map($raw));
        $unpacked = (new BufferUnpacker($packed))->unpack();

        self::assertSame($raw, $unpacked);
    }
}
