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

use MessagePack\Packer;
use MessagePack\Type\Map;
use MessagePack\TypeTransformer\MapTransformer;
use PHPUnit\Framework\TestCase;

class MapTransformerTest extends TestCase
{
    public function testPackMap() : void
    {
        $raw = ['abc' => 5];
        $packed = "\x81\xa3\x61\x62\x63\x05";

        $packer = $this->createMock(Packer::class);
        $packer->expects(self::once())->method('packMap')
            ->with($raw)
            ->willReturn($packed);

        $transformer = new MapTransformer();

        self::assertSame($packed, $transformer->pack($packer, new Map($raw)));
    }

    public function testPackNonMap() : void
    {
        $packer = $this->createMock(Packer::class);
        $packer->expects(self::never())->method('packMap');

        $transformer = new MapTransformer();

        self::assertNull($transformer->pack($packer, ['abc' => 5]));
    }
}
