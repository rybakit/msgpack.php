<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\TypeTransformer;

use MessagePack\Type\Map;
use MessagePack\TypeTransformer\MapTransformer;

class MapTransformerTest extends \PHPUnit_Framework_TestCase
{
    public function testPackMap()
    {
        $raw = ['abc' => 5];
        $packed = "\x81\xa3\x61\x62\x63\x05";

        $packer = $this->getMockBuilder('MessagePack\Packer')->getMock();
        $packer->expects(self::any())->method('packMap')
            ->with($raw)
            ->willReturn($packed);

        $transformer = new MapTransformer();
        $map = new Map($raw);

        self::assertSame($packed, $transformer->pack($packer, $map));
    }

    public function testPackNonMap()
    {
        $raw = ['abc' => 5];
        $packed = "\x81\xa3\x61\x62\x63\x05";

        $packer = $this->getMockBuilder('MessagePack\Packer')->getMock();
        $packer->expects(self::any())->method('packMap')
            ->with($raw)
            ->willReturn($packed);

        $transformer = new MapTransformer();

        self::assertNull($transformer->pack($packer, $raw));
    }
}
