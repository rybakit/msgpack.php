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

use MessagePack\BufferUnpacker;
use MessagePack\MessagePack;
use MessagePack\Packer;

class MessagePackTest extends \PHPUnit_Framework_TestCase
{
    public function testPack()
    {
        $this->assertSame("\x91\x01", MessagePack::pack([0 => 1]));
    }

    public function testPackWithArgument()
    {
        $this->assertSame("\x81\x00\x01", MessagePack::pack([0 => 1], Packer::FORCE_MAP));
    }

    public function testUnpack()
    {
        $this->assertSame('abc', MessagePack::unpack("\xa3\x61\x62\x63"));
    }

    public function testUnpackWithArgument()
    {
        $packed = "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff";
        $unpacked = '18446744073709551615';

        $this->assertSame($unpacked, MessagePack::unpack($packed, BufferUnpacker::INT_AS_STR));
    }
}
