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
use MessagePack\Type\Bin;
use PHPUnit\Framework\TestCase;

final class BinTest extends TestCase
{
    public function testConstructor() : void
    {
        $data = 'abc';
        $bin = new Bin($data);

        self::assertSame($data, $bin->data);
    }

    public function testCanBePacked() : void
    {
        $data = 'abc';
        $packer = new Packer();

        $packed = $packer->pack(new Bin($data));
        $unpacked = (new BufferUnpacker($packed))->unpack();

        self::assertSame($data, $unpacked);
    }
}
