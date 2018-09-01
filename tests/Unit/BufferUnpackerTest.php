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
use MessagePack\Exception\InsufficientDataException;
use MessagePack\Exception\IntegerOverflowException;
use MessagePack\Exception\InvalidOptionException;
use MessagePack\Exception\UnpackingFailedException;
use MessagePack\Ext;
use MessagePack\TypeTransformer\Unpackable;
use MessagePack\UnpackOptions;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

final class BufferUnpackerTest extends TestCase
{
    /**
     * @var BufferUnpacker
     */
    private $unpacker;

    protected function setUp() : void
    {
        $this->unpacker = new BufferUnpacker();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideUnpackData
     */
    public function testUnpack($raw, string $packed) : void
    {
        $this->unpacker->reset($packed);
        $isOrHasObject = is_object($raw) || is_array($raw);

        $isOrHasObject
            ? self::assertEquals($raw, $this->unpacker->unpack())
            : self::assertSame($raw, $this->unpacker->unpack());
    }

    /**
     * @dataProvider provideInsufficientData
     */
    public function testUnpackInsufficientData(string $data, int $expectedLength, int $actualLength) : void
    {
        try {
            $this->unpacker->reset($data)->unpack();
        } catch (InsufficientDataException $e) {
            self::assertSame("Not enough data to unpack: expected $expectedLength, got $actualLength.", $e->getMessage());

            return;
        }

        self::fail(InsufficientDataException::class.' was not thrown.');
    }

    public function provideInsufficientData() : array
    {
        return [
            'str'       => ['', 1, 0],
            'uint8'     => ["\xcc", 1, 0],
            'uint16'    => ["\xcd", 2, 0],
            'uint32'    => ["\xce", 4, 0],
            'uint64'    => ["\xcf", 8, 0],
            'in8'       => ["\xd0", 1, 0],
            'int16'     => ["\xd1", 2, 0],
            'int32'     => ["\xd2", 4, 0],
            'int64'     => ["\xd3", 8, 0],
            'float32'   => ["\xca", 4, 0],
            'float64'   => ["\xcb", 8, 0],
            'fixext1'   => ["\xd4", 1, 0],
            'fixext2'   => ["\xd5", 2, 0],
            'fixext4'   => ["\xd6", 4, 0],
            'fixext8'   => ["\xd7", 8, 0],
            'fixext16'  => ["\xd8", 16, 0],
            'ext8'      => ["\xc7", 1, 0],
            'ext16'     => ["\xc8", 2, 0],
            'ext32'     => ["\xc9", 4, 0],
        ];
    }

    public function testUnpackUnknownCode() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unknown code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpack();
    }

    public function testUnpackBigIntAsException() : void
    {
        $this->expectException(IntegerOverflowException::class);
        $this->expectExceptionMessage('The value is too big: 18446744073709551615.');

        $unpacker = new BufferUnpacker(
            "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff",
            UnpackOptions::BIGINT_AS_EXCEPTION
        );

        $unpacker->unpack();
    }

    public function testUnpackBigIntAsString() : void
    {
        $unpacker = new BufferUnpacker(
            "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff",
            UnpackOptions::BIGINT_AS_STR
        );

        self::assertSame('18446744073709551615', $unpacker->unpack());
    }

    public function testUnpackBigIntDefaultModeString() : void
    {
        $unpacker = new BufferUnpacker("\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff");

        self::assertSame('18446744073709551615', $unpacker->unpack());
    }

    /**
     * @requires extension gmp
     */
    public function testUnpackBigIntAsGmp() : void
    {
        $unpacker = new BufferUnpacker(
            "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff",
            UnpackOptions::BIGINT_AS_GMP
        );

        $uint64 = $unpacker->unpack();

        self::assertInstanceOf(\GMP::class, $uint64);
        self::assertSame('18446744073709551615', gmp_strval($uint64));
    }

    public function testReset() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->append("\xc3")->reset()->unpack();
    }

    public function testResetWithBuffer() : void
    {
        $this->unpacker->append("\xc2")->reset("\xc3");

        self::assertTrue($this->unpacker->unpack());
    }

    public function testSeek() : void
    {
        $this->unpacker->append("\xc2\xc2\xc3")->seek(2);

        self::assertTrue($this->unpacker->unpack());
    }

    public function testSeekFromEnd() : void
    {
        $this->unpacker->append("\xc2\xc2\xc3");
        $this->unpacker->seek(-1);

        self::assertTrue($this->unpacker->unpack());
    }

    public function testSeekInvalidOffset() : void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('Unable to seek to position 10.');

        $this->unpacker->append("\xc2")->unpack();
        $this->unpacker->seek(10);
    }

    public function testSkip() : void
    {
        $this->unpacker->append("\xc2\xc2\xc3");
        $this->unpacker->unpackBool();
        $this->unpacker->skip(1);

        self::assertTrue($this->unpacker->unpack());
    }

    public function testSkipInvalidOffset() : void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('Unable to seek to position 21.');

        $this->unpacker->append("\xc2")->unpack();
        $this->unpacker->skip(20);
    }

    public function testClone() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->reset("\xc3");

        $clone = clone $this->unpacker;
        $clone->unpack();
    }

    public function testTryUnpack() : void
    {
        $foo = [1, 2];
        $bar = 'bar';
        $packed = "\x92\x01\x02\xa3\x62\x61\x72";

        $this->unpacker->append($packed[0]);
        self::assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[1]);
        self::assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[2].$packed[3]);
        self::assertSame([$foo], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[4].$packed[5]);
        self::assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[6]);
        self::assertSame([$bar], $this->unpacker->tryUnpack());
    }

    public function testTryUnpackReturnsAllUnpackedData() : void
    {
        $foo = [1, 2];
        $bar = 'bar';
        $packed = "\x92\x01\x02\xa3\x62\x61\x72";

        $this->unpacker->append($packed);
        self::assertSame([$foo, $bar], $this->unpacker->tryUnpack());
    }

    public function testTryUnpackTruncatesBuffer() : void
    {
        $this->unpacker->append("\xc3");

        self::assertSame([true], $this->unpacker->tryUnpack());

        try {
            $this->unpacker->unpack();
        } catch (InsufficientDataException $e) {
            self::assertSame('Not enough data to unpack: expected 1, got 0.', $e->getMessage());

            return;
        }

        self::fail('Buffer was not truncated.');
    }

    /**
     * @dataProvider provideInvalidOptionsData
     */
    public function testConstructorThrowsErrorOnInvalidOptions($options) : void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageRegExp('/Invalid option .+?, use .+?\./');

        new BufferUnpacker('', $options);
    }

    public function provideInvalidOptionsData() : array
    {
        return [
            [UnpackOptions::BIGINT_AS_STR | UnpackOptions::BIGINT_AS_GMP],
            [UnpackOptions::BIGINT_AS_STR | UnpackOptions::BIGINT_AS_EXCEPTION],
            [UnpackOptions::BIGINT_AS_GMP | UnpackOptions::BIGINT_AS_EXCEPTION],
            [UnpackOptions::BIGINT_AS_STR | UnpackOptions::BIGINT_AS_GMP | UnpackOptions::BIGINT_AS_EXCEPTION],
        ];
    }

    public function testBadKeyTypeThrowsWarning() : void
    {
        $this->expectException(Warning::class);
        $this->expectExceptionMessage('Illegal offset type');

        $this->unpacker->reset("\x81\x82\x00\x01\x01\x02\x00"); // [[1, 2] => 0]

        $this->unpacker->unpack();
    }

    public function testBadKeyTypeIsIgnored() : void
    {
        $this->unpacker->reset("\x82\x82\x00\x01\x01\x02\x00\x04\x02"); // [[1, 2] => 0, 4 => 2]
        $raw = @$this->unpacker->unpack();

        self::assertSame([4 => 2], $raw);
    }

    public function testUnpackCustomType() : void
    {
        $obj = new \stdClass();
        $type = 5;

        $transformer = $this->createMock(Unpackable::class);
        $transformer->expects(self::any())->method('getType')->willReturn($type);
        $transformer->expects(self::once())->method('unpack')
            ->with($this->unpacker, 1)
            ->willReturn($obj);

        $this->unpacker->registerTransformer($transformer);

        self::assertSame($obj, $this->unpacker->reset("\xd4\x05\x01")->unpack());
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideNilData
     */
    public function testUnpackNil($raw, string $packed) : void
    {
        self::assertNull($this->unpacker->reset($packed)->unpackNil());
    }

    public function testUnpackInsufficientNil() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->unpackNil();
    }

    public function testUnpackInvalidNil() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected nil code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpackNil();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideBoolData
     */
    public function testUnpackBool(bool $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackBool());
    }

    public function testUnpackInsufficientBool() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->unpackBool();
    }

    public function testUnpackInvalidBool() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected bool code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpackBool();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideIntUnpackData
     */
    public function testUnpackInt(int $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackInt());
    }

    public function testUnpackInsufficientInt() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->unpackInt();
    }

    public function testUnpackInvalidInt() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected int code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpackInt();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideFloatUnpackData
     */
    public function testUnpackFloat(float $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackFloat());
    }

    public function testUnpackInsufficientFloat() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->unpackFloat();
    }

    public function testUnpackInvalidFloat() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected float code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpackFloat();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideStrData
     */
    public function testUnpackStr(string $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackStr());
    }

    public function testUnpackInsufficientStr() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->unpackStr();
    }

    public function testUnpackInvalidStr() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected str code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpackStr();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideBinData
     */
    public function testUnpackBin(string $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackBin());
    }

    public function testUnpackInsufficientBin() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->unpackBin();
    }

    public function testUnpackInvalidBin() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected bin code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpackBin();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideArrayData
     */
    public function testUnpackArray(array $raw, string $packed) : void
    {
        self::assertEquals($raw, $this->unpacker->reset($packed)->unpackArray());
    }

    public function testUnpackInsufficientArray() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->unpackArray();
    }

    public function testUnpackInvalidArray() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected array header code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpackArray();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideMapUnpackData
     */
    public function testUnpackMap(array $raw, string $packed) : void
    {
        self::assertEquals($raw, $this->unpacker->reset($packed)->unpackMap());
    }

    public function testUnpackInsufficientMap() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->unpackMap();
    }

    public function testUnpackInvalidMap() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected map header code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpackMap();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideExtData
     */
    public function testUnpackExt(Ext $raw, string $packed) : void
    {
        self::assertEquals($raw, $this->unpacker->reset($packed)->unpackExt());
    }

    public function testUnpackInsufficientExt() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to unpack: expected 1, got 0.');

        $this->unpacker->unpackExt();
    }

    public function testUnpackInvalidExt() : void
    {
        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected ext header code: 0xc1.');

        $this->unpacker->reset("\xc1")->unpackExt();
    }
}
