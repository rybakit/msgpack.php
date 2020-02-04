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
use MessagePack\TypeTransformer\StreamTransformer;
use PHPUnit\Framework\TestCase;

final class StreamTransformerTest extends TestCase
{
    public function testPackStream() : void
    {
        $raw = "\x80";
        $packed = "\xc4\x01\x80";

        $packer = $this->createMock(Packer::class);
        $packer->expects(self::once())->method('packBin')
            ->with($raw)
            ->willReturn($packed);

        $transformer = new StreamTransformer();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        self::assertSame($packed, $transformer->pack($packer, $stream));
    }

    public function testPackNonResource() : void
    {
        $packer = $this->createMock(Packer::class);
        $packer->expects(self::never())->method('packBin');

        $transformer = new StreamTransformer();

        self::assertNull($transformer->pack($packer, 'str'));
    }

    public function testPackNonStreamResource() : void
    {
        $packer = $this->createMock(Packer::class);
        $packer->expects(self::never())->method('packBin');

        $transformer = new StreamTransformer();

        self::assertNull($transformer->pack($packer, stream_context_create()));
    }
}
