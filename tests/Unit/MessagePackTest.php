<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit;

use MessagePack\MessagePack;
use MessagePack\PackOptions;
use MessagePack\UnpackOptions;
use PHPUnit\Framework\TestCase;

final class MessagePackTest extends TestCase
{
    public function testPack() : void
    {
        self::assertSame("\x91\x01", MessagePack::pack([0 => 1]));
    }

    public function testPackWithOptions() : void
    {
        self::assertSame("\x81\x00\x01", MessagePack::pack([0 => 1], PackOptions::FORCE_MAP));
    }

    public function testUnpack() : void
    {
        self::assertSame('abc', MessagePack::unpack("\xa3\x61\x62\x63"));
    }

    public function testUnpackWithOptions() : void
    {
        $packed = "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff";
        $unpacked = '18446744073709551615';

        self::assertSame($unpacked, MessagePack::unpack($packed, UnpackOptions::BIGINT_AS_STR));
    }
}
