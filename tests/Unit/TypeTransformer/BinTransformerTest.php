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
use MessagePack\Type\Bin;
use MessagePack\TypeTransformer\BinTransformer;
use PHPUnit\Framework\TestCase;

final class BinTransformerTest extends TestCase
{
    public function testPackBinary() : void
    {
        $raw = 'abc';
        $packed = "\xc4\x03\x61\x62\x63";

        $packer = $this->createMock(Packer::class);
        $packer->expects(self::once())->method('packBin')
            ->with($raw)
            ->willReturn($packed);

        $transformer = new BinTransformer();

        self::assertSame($packed, $transformer->pack($packer, new Bin($raw)));
    }

    public function testPackNonBinary() : void
    {
        $packer = $this->createMock(Packer::class);
        $packer->expects(self::never())->method('packBin');

        $transformer = new BinTransformer();

        self::assertNull($transformer->pack($packer, 'abc'));
    }
}
