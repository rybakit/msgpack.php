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
use MessagePack\Type\Ext;
use PHPUnit\Framework\TestCase;

final class ExtTest extends TestCase
{
    public function testConstructor() : void
    {
        $type = 3;
        $data = "\xaa";
        $ext = new Ext($type, $data);

        self::assertSame($type, $ext->type);
        self::assertSame($data, $ext->data);
    }

    public function testCanBePacked() : void
    {
        $type = 3;
        $data = "\xaa";
        $packer = new Packer();

        $packed = $packer->pack(new Ext($type, $data));
        $unpacked = (new BufferUnpacker($packed))->unpack();

        self::assertInstanceOf(Ext::class, $unpacked);
        self::assertSame($type, $unpacked->type);
        self::assertSame($data, $unpacked->data);
    }
}
