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

use Decimal\Decimal;
use MessagePack\BufferUnpacker;
use MessagePack\Exception\InsufficientDataException;
use MessagePack\Exception\InvalidOptionException;
use MessagePack\Exception\UnpackingFailedException;
use MessagePack\Ext;
use MessagePack\Tests\DataProvider;
use MessagePack\TypeTransformer\Extension;
use MessagePack\UnpackOptions;
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

        self::assertFalse($this->unpacker->hasRemaining());
    }

    /**
     * @dataProvider provideInsufficientData
     */
    public function testUnpackThrowsExceptionOnInsufficientData(string $data) : void
    {
        $this->unpacker->reset($data);

        try {
            $this->unpacker->unpack();
        } catch (InsufficientDataException $e) {
            self::assertSame('Not enough data to read.', $e->getMessage());

            return;
        }

        self::fail(InsufficientDataException::class.' was not thrown.');
    }

    public function provideInsufficientData() : array
    {
        return [
            'str' => [''],
            'uint8' => ["\xcc"],
            'uint16' => ["\xcd"],
            'uint32' => ["\xce"],
            'uint64' => ["\xcf"],
            'in8' => ["\xd0"],
            'int16' => ["\xd1"],
            'int32' => ["\xd2"],
            'int64' => ["\xd3"],
            'float32' => ["\xca"],
            'float64' => ["\xcb"],
            'fixext1' => ["\xd4"],
            'fixext1t' => ["\xd4\x01"],
            'fixext2' => ["\xd5"],
            'fixext2t' => ["\xd5\x01"],
            'fixext4' => ["\xd6"],
            'fixext4t' => ["\xd6\x01"],
            'fixext8' => ["\xd7"],
            'fixext8t' => ["\xd7\x01"],
            'fixext16' => ["\xd8"],
            'fixext16t' => ["\xd8\x01"],
            'ext8' => ["\xc7"],
            'ext8l' => ["\xc7\xff"],
            'ext8lt' => ["\xc7\xff\x01"],
            'ext16' => ["\xc8"],
            'ext16l' => ["\xc8\xff\xff"],
            'ext16lt' => ["\xc8\xff\xff\x01"],
            'ext32' => ["\xc9"],
            'ext32l' => ["\xc9\xff\xff\xff\xff"],
            'ext32lt' => ["\xc9\xff\xff\xff\xff\x01"],
        ];
    }

    public function testUnpackThrowsExceptionOnUnknownCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unknown code: 0xc1.');

        $this->unpacker->unpack();
    }

    public function testUnpackBigIntDefaultMode() : void
    {
        $unpacker = new BufferUnpacker("\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff");

        self::assertSame('18446744073709551615', $unpacker->unpack());
    }

    public function testUnpackBigIntAsStr() : void
    {
        $unpacker = new BufferUnpacker(
            "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff",
            UnpackOptions::BIGINT_AS_STR
        );

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

    /**
     * @requires extension decimal
     */
    public function testUnpackBigIntAsDec() : void
    {
        $unpacker = new BufferUnpacker(
            "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff",
            UnpackOptions::BIGINT_AS_DEC
        );

        $uint64 = $unpacker->unpack();

        self::assertInstanceOf(Decimal::class, $uint64);
        self::assertSame('18446744073709551615', $uint64->toString());
    }

    public function testResetEmptiesBuffer() : void
    {
        $this->unpacker->append("\xc3");
        self::assertSame(1, $this->unpacker->getRemainingCount());

        $this->unpacker->reset();
        self::assertSame(0, $this->unpacker->getRemainingCount());
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

    public function testSeekThrowsExceptionOnInvalidOffset() : void
    {
        $this->unpacker->append("\xc2")->unpack();

        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Unable to seek to position 10.');

        $this->unpacker->seek(10);
    }

    public function testSkip() : void
    {
        $this->unpacker->append("\xc2\xc2\xc3");
        $this->unpacker->unpackBool();
        $this->unpacker->skip(1);

        self::assertTrue($this->unpacker->unpack());
    }

    public function testSkipThrowsExceptionOnInvalidOffset() : void
    {
        $this->unpacker->append("\xc2")->unpack();

        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Unable to seek to position 21.');

        $this->unpacker->skip(20);
    }

    public function testRemaining() : void
    {
        self::assertSame(0, $this->unpacker->getRemainingCount());
        self::assertFalse($this->unpacker->hasRemaining());

        $this->unpacker->reset("\x01\x02");

        self::assertSame(1, $this->unpacker->unpack());
        self::assertSame(1, $this->unpacker->getRemainingCount());
        self::assertTrue($this->unpacker->hasRemaining());

        self::assertSame(2, $this->unpacker->unpack());
        self::assertSame(0, $this->unpacker->getRemainingCount());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testRelease() : void
    {
        self::assertSame(0, $this->unpacker->release());
        $this->unpacker->reset("\x01\x02");
        self::assertSame(0, $this->unpacker->release());

        self::assertSame(1, $this->unpacker->unpack());
        self::assertSame(1, $this->unpacker->release());

        self::assertSame(2, $this->unpacker->unpack());
        self::assertSame(1, $this->unpacker->release());

        self::assertSame(0, $this->unpacker->release());
    }

    public function testClone() : void
    {
        $this->unpacker->reset("\xc3");
        $unpacker = clone $this->unpacker;

        self::assertTrue($unpacker->unpack());
    }

    public function testCloneWithBuffer() : void
    {
        $this->unpacker->reset("\xc3");
        $unpacker = $this->unpacker->withBuffer("\xc2");

        self::assertFalse($unpacker->unpack());
    }

    public function testCloneWithEmptyBuffer() : void
    {
        $this->unpacker->reset("\xc3");
        $unpacker = $this->unpacker->withBuffer('');

        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $unpacker->unpack();
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
        self::assertTrue($this->unpacker->hasRemaining());

        $this->unpacker->append($packed[4].$packed[5]);
        self::assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[6]);
        self::assertSame([$bar], $this->unpacker->tryUnpack());
        self::assertFalse($this->unpacker->hasRemaining());
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
            self::assertSame('Not enough data to read.', $e->getMessage());

            return;
        }

        self::fail('Buffer was not truncated.');
    }

    /**
     * @dataProvider provideInvalidOptionsData
     */
    public function testConstructorThrowsExceptionOnInvalidOptions($options) : void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageRegExp('/Invalid option .+?, use .+?\./');

        new BufferUnpacker('', $options);
    }

    public function provideInvalidOptionsData() : iterable
    {
        return [
            [UnpackOptions::BIGINT_AS_STR | UnpackOptions::BIGINT_AS_GMP],
            [UnpackOptions::BIGINT_AS_STR | UnpackOptions::BIGINT_AS_DEC],
            [UnpackOptions::BIGINT_AS_GMP | UnpackOptions::BIGINT_AS_DEC],
            [UnpackOptions::BIGINT_AS_STR | UnpackOptions::BIGINT_AS_GMP | UnpackOptions::BIGINT_AS_DEC],
        ];
    }

    public function testConstructorSetsTransformers() : void
    {
        $obj = new \stdClass();
        $type = 5;

        $transformer = $this->createMock(Extension::class);
        $transformer->method('getType')->willReturn($type);
        $transformer->expects(self::once())->method('unpackExt')
            ->with($this->isInstanceOf(BufferUnpacker::class), 1)
            ->willReturn($obj);

        $unpacker = new BufferUnpacker('', null, [$transformer]);

        self::assertSame($obj, $unpacker->reset("\xd4\x05\x01")->unpack());
    }

    public function testUnpackCustomType() : void
    {
        $obj1 = new \stdClass();
        $obj2 = new \ArrayObject();

        $type1 = 5;
        $type2 = 6;

        $extension1 = $this->createMock(Extension::class);
        $extension1->method('getType')->willReturn($type1);
        $extension1->expects(self::once())->method('unpackExt')
            ->with($this->isInstanceOf(BufferUnpacker::class), 1)
            ->willReturn($obj1);

        $extension2 = $this->createMock(Extension::class);
        $extension2->method('getType')->willReturn($type2);
        $extension2->expects(self::once())->method('unpackExt')
            ->with($this->isInstanceOf(BufferUnpacker::class), 1)
            ->willReturn($obj2);

        $unpacker = $this->unpacker->extendWith($extension1, $extension2);

        self::assertSame($obj1, $unpacker->reset("\xd4\x05\x01")->unpack());
        self::assertSame($obj2, $unpacker->reset("\xd4\x06\x01")->unpack());
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideNilData
     */
    public function testUnpackNil($raw, string $packed) : void
    {
        self::assertNull($this->unpacker->reset($packed)->unpackNil());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testUnpackNilThrowsExceptionOnInsufficientData() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $this->unpacker->unpackNil();
    }

    public function testUnpackNilThrowsExceptionOnUnexpectedCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected nil code: 0xc1.');

        $this->unpacker->unpackNil();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideBoolData
     */
    public function testUnpackBool(bool $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackBool());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testUnpackBoolThrowsExceptionOnInsufficientData() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $this->unpacker->unpackBool();
    }

    public function testUnpackBoolThrowsExceptionOnUnexpectedCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected bool code: 0xc1.');

        $this->unpacker->unpackBool();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideIntUnpackData
     */
    public function testUnpackInt(int $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackInt());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testUnpackIntThrowsExceptionOnInsufficientData() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $this->unpacker->unpackInt();
    }

    public function testUnpackIntThrowsExceptionOnUnexpectedCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected int code: 0xc1.');

        $this->unpacker->unpackInt();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideFloatUnpackData
     */
    public function testUnpackFloat(float $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackFloat());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testUnpackFloatThrowsExceptionOnInsufficientData() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $this->unpacker->unpackFloat();
    }

    public function testUnpackFloatThrowsExceptionOnUnexpectedCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected float code: 0xc1.');

        $this->unpacker->unpackFloat();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideStrData
     */
    public function testUnpackStr(string $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackStr());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testUnpackStrThrowsExceptionOnInsufficientData() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $this->unpacker->unpackStr();
    }

    public function testUnpackStrThrowsExceptionOnUnexpectedCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected str code: 0xc1.');

        $this->unpacker->unpackStr();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideBinData
     */
    public function testUnpackBin(string $raw, string $packed) : void
    {
        self::assertSame($raw, $this->unpacker->reset($packed)->unpackBin());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testUnpackBinThrowsExceptionOnInsufficientData() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $this->unpacker->unpackBin();
    }

    public function testUnpackBinThrowsExceptionOnUnexpectedCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected bin code: 0xc1.');

        $this->unpacker->unpackBin();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideArrayData
     */
    public function testUnpackArray(array $raw, string $packed) : void
    {
        self::assertEquals($raw, $this->unpacker->reset($packed)->unpackArray());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testUnpackArrayThrowsExceptionOnInsufficientData() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $this->unpacker->unpackArray();
    }

    public function testUnpackArrayThrowsExceptionOnUnexpectedCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected array code: 0xc1.');

        $this->unpacker->unpackArray();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideMapUnpackData
     */
    public function testUnpackMap(array $raw, string $packed) : void
    {
        self::assertEquals($raw, $this->unpacker->reset($packed)->unpackMap());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testUnpackMapThrowsExceptionOnInsufficientData() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $this->unpacker->unpackMap();
    }

    public function testUnpackMapThrowsExceptionOnUnexpectedCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected map code: 0xc1.');

        $this->unpacker->unpackMap();
    }

    /**
     * @dataProvider provideMapWithInvalidKeyData
     */
    public function testUnpackMapThrowsExceptionOnInvalidMapKey(string $packedMap, string $invalidType) : void
    {
        $this->unpacker->reset($packedMap);

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage(sprintf('Invalid map key type: expected int, str or bin but got %s', $invalidType));

        $this->unpacker->unpackMap();
    }

    /**
     * @dataProvider provideMapWithInvalidKeyData
     */
    public function testUnpackThrowsExceptionOnInvalidMapKey(string $packedMap, string $invalidType) : void
    {
        $this->unpacker->reset($packedMap);

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage(sprintf('Invalid map key type: expected int, str or bin but got %s', $invalidType));

        $this->unpacker->unpack();
    }

    public function provideMapWithInvalidKeyData() : iterable
    {
        $data = static function () {
            yield 'nil' => DataProvider::provideNilData();
            yield 'bool' => DataProvider::provideBoolData();
            yield 'float' => DataProvider::provideFloatUnpackData();
            yield 'array' => DataProvider::provideArrayData();
            yield 'map' => DataProvider::provideMapUnpackData();
            yield 'ext' => DataProvider::provideExtData();
        };

        foreach ($data() as $type => $items) {
            foreach ($items as [$raw, $packed]) {
                yield ["\x81{$packed}\x00", $type]; // [$raw => 0]
            }
        }
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideExtData
     */
    public function testUnpackExt(Ext $raw, string $packed) : void
    {
        self::assertEquals($raw, $this->unpacker->reset($packed)->unpackExt());
        self::assertFalse($this->unpacker->hasRemaining());
    }

    public function testUnpackExtThrowsExceptionOnInsufficientData() : void
    {
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Not enough data to read.');

        $this->unpacker->unpackExt();
    }

    public function testUnpackExtThrowsExceptionOnUnexpectedCode() : void
    {
        $this->unpacker->reset("\xc1");

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('Unexpected ext code: 0xc1.');

        $this->unpacker->unpackExt();
    }

    /**
     * @dataProvider provideInvalidExtBodyData
     */
    public function testUnpackExtAllowsZeroLengthExtData(string $data) : void
    {
        $ext = $this->unpacker->reset($data)->unpackExt();

        self::assertSame('', $ext->data);
    }

    /**
     * @dataProvider provideInvalidExtBodyData
     */
    public function testUnpackAllowsZeroLengthExtData(string $data) : void
    {
        $ext = $this->unpacker->reset($data)->unpack();

        self::assertSame('', $ext->data);
    }

    public function provideInvalidExtBodyData() : iterable
    {
        return [
            'ext8' => ["\xc7\x00\x01"],
            'ext16' => ["\xc8\x00\x00\x01"],
            'ext32' => ["\xc9\x00\x00\x00\x00\x01"],
        ];
    }
}
