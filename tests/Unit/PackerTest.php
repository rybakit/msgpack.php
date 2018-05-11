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

use MessagePack\Exception\InvalidOptionException;
use MessagePack\Exception\PackingFailedException;
use MessagePack\Ext;
use MessagePack\Packer;
use MessagePack\PackOptions;
use MessagePack\TypeTransformer\Packable;
use PHPUnit\Framework\TestCase;

final class PackerTest extends TestCase
{
    /**
     * @var Packer
     */
    private $packer;

    protected function setUp() : void
    {
        $this->packer = new Packer();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideData
     */
    public function testPack($raw, string $packed) : void
    {
        self::assertSame($packed, $this->packer->pack($raw));
    }

    /**
     * @dataProvider provideUnsupportedTypeData
     */
    public function testPackUnsupportedType($value, string $type) : void
    {
        $this->expectException(PackingFailedException::class);
        $this->expectExceptionMessage("Unsupported type: $type.");

        $this->packer->pack($value);
    }

    public function provideUnsupportedTypeData() : array
    {
        return [
            [tmpfile(), 'resource'],
            [new \stdClass(), 'stdClass'],
        ];
    }

    /**
     * @dataProvider provideOptionsData
     */
    public function testConstructorSetOptions($options, $raw, string $packed) : void
    {
        self::assertSame($packed, (new Packer($options))->pack($raw));
    }

    public function provideOptionsData() : array
    {
        return [
            [null, "\x80", "\xc4\x01\x80"],
            [null, 'a', "\xa1\x61"],
            [null, 2.5, "\xcb\x40\x04\x00\x00\x00\x00\x00\x00"],
            [null, [1 => 2], "\x81\x01\x02"],
            [null, [0 => 1], "\x91\x01"],
            [PackOptions::DETECT_STR_BIN, "\x80", "\xc4\x01\x80"],
            [PackOptions::DETECT_STR_BIN, 'a', "\xa1\x61"],
            [PackOptions::FORCE_FLOAT64, 0.0, "\xcb\x00\x00\x00\x00\x00\x00\x00\x00"],
            [PackOptions::DETECT_ARR_MAP, [1 => 2], "\x81\x01\x02"],
            [PackOptions::DETECT_ARR_MAP, [0 => 1], "\x91\x01"],
            [PackOptions::FORCE_STR, "\x80", "\xa1\x80"],
            [PackOptions::FORCE_BIN, 'a', "\xc4\x01\x61"],
            [PackOptions::FORCE_ARR, [1 => 2], "\x91\x02"],
            [PackOptions::FORCE_MAP, [0 => 1], "\x81\x00\x01"],
            [PackOptions::FORCE_FLOAT32, 2.5, "\xca\x40\x20\x00\x00"],
            [PackOptions::FORCE_STR | PackOptions::FORCE_ARR, [1 => "\x80"], "\x91\xa1\x80"],
            [PackOptions::FORCE_STR | PackOptions::FORCE_MAP, [0 => "\x80"], "\x81\x00\xa1\x80"],
            [PackOptions::FORCE_BIN | PackOptions::FORCE_ARR, [1 => 'a'], "\x91\xc4\x01\x61"],
            [PackOptions::FORCE_BIN | PackOptions::FORCE_MAP, [0 => 'a'], "\x81\x00\xc4\x01\x61"],
        ];
    }

    /**
     * @dataProvider provideInvalidOptionsData
     */
    public function testConstructorThrowsErrorOnInvalidOptions($options) : void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageRegExp('/Invalid option .+?, use .+?\./');

        new Packer($options);
    }

    public function provideInvalidOptionsData() : array
    {
        return [
            [PackOptions::FORCE_STR | PackOptions::FORCE_BIN],
            [PackOptions::FORCE_STR | PackOptions::FORCE_BIN | PackOptions::DETECT_STR_BIN],
            [PackOptions::FORCE_ARR | PackOptions::FORCE_MAP],
            [PackOptions::FORCE_ARR | PackOptions::FORCE_MAP | PackOptions::DETECT_ARR_MAP],
            [PackOptions::FORCE_STR | PackOptions::FORCE_BIN | PackOptions::DETECT_STR_BIN | PackOptions::FORCE_ARR | PackOptions::FORCE_MAP | PackOptions::DETECT_ARR_MAP],
        ];
    }

    public function testPackCustomType() : void
    {
        $obj = new \stdClass();
        $packed = 'packed';

        $transformer = $this->createMock(Packable::class);
        $transformer->expects(self::once())->method('pack')
            ->with($this->packer, $obj)
            ->willReturn($packed);

        $this->packer->registerTransformer($transformer);

        self::assertSame($packed, $this->packer->pack($obj));
    }

    public function testPackCustomUnsupportedType() : void
    {
        $this->expectException(PackingFailedException::class);
        $this->expectExceptionMessage('Unsupported type: stdClass.');

        $obj = new \stdClass();

        $transformer = $this->createMock(Packable::class);
        $transformer->expects(self::atLeastOnce())->method('pack')
            ->with($this->packer, $obj)
            ->willReturn(null);

        $this->packer->registerTransformer($transformer);
        $this->packer->pack($obj);
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideNilData
     */
    public function testPackNil($raw, string $packed) : void
    {
        self::assertSame($packed, $this->packer->packNil());
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideBoolData()
     */
    public function testPackBool($raw, string $packed) : void
    {
        self::assertSame($packed, $this->packer->packBool($raw));
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideIntData
     */
    public function testPackInt(int $raw, string $packed) : void
    {
        self::assertSame($packed, $this->packer->packInt($raw));
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideFloatData
     */
    public function testPackFloat(float $raw, string $packed) : void
    {
        self::assertSame($packed, $this->packer->packFloat($raw));
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideStrData
     */
    public function testPackStr(string $raw, string $packed) : void
    {
        self::assertSame($packed, $this->packer->packStr($raw));
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideBinData
     */
    public function testPackBin(string $raw, string $packed) : void
    {
        self::assertSame($packed, $this->packer->packBin($raw));
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideArrayData
     */
    public function testPackArray(array $raw, string $packed) : void
    {
        self::assertEquals($packed, $this->packer->packArray($raw));
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideMapData
     */
    public function testPackMap(array $raw, string $packed) : void
    {
        self::assertEquals($packed, $this->packer->packMap($raw));
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideExtData
     */
    public function testPackExt(Ext $raw, string $packed) : void
    {
        self::assertEquals($packed, $this->packer->packExt($raw->type, $raw->data));
    }
}
