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

use MessagePack\Packer;
use MessagePack\PackOptions;

class PackerTest extends \PHPUnit_Framework_TestCase
{
    use TransformerUtils;

    /**
     * @var Packer
     */
    private $packer;

    protected function setUp()
    {
        $this->packer = new Packer();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideData
     */
    public function testPack($title, $raw, $packed)
    {
        $this->assertSame($packed, $this->packer->pack($raw));
    }

    /**
     * @dataProvider provideUnsupportedValues
     * @expectedException \MessagePack\Exception\PackingFailedException
     * @expectedExceptionMessage Unsupported type.
     */
    public function testPackUnsupportedType($value)
    {
        $this->packer->pack($value);
    }

    public function provideUnsupportedValues()
    {
        return [
            [\tmpfile()],
            [new \stdClass()],
        ];
    }

    /**
     * @dataProvider provideOptionsData
     */
    public function testConstructorSetOptions($options, $raw, $packed)
    {
        $this->assertSame($packed, (new Packer($options))->pack($raw));
    }

    public function provideOptionsData()
    {
        return [
            [null, "\x80", "\xc4\x01\x80"],
            [null, 'a', "\xa1\x61"],
            [null, 0.0, "\xcb\x00\x00\x00\x00\x00\x00\x00\x00"],
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
            [PackOptions::FORCE_FLOAT32, 0.0, "\xca\x00\x00\x00\x00"],
            [PackOptions::FORCE_STR | PackOptions::FORCE_ARR, [1 => "\x80"], "\x91\xa1\x80"],
            [PackOptions::FORCE_STR | PackOptions::FORCE_MAP, [0 => "\x80"], "\x81\x00\xa1\x80"],
            [PackOptions::FORCE_BIN | PackOptions::FORCE_ARR, [1 => 'a'], "\x91\xc4\x01\x61"],
            [PackOptions::FORCE_BIN | PackOptions::FORCE_MAP, [0 => 'a'], "\x81\x00\xc4\x01\x61"],
        ];
    }

    /**
     * @dataProvider provideInvalidOptionsData
     * @expectedException \MessagePack\Exception\InvalidOptionException
     * @expectedExceptionMessageRegExp /Invalid option .+?, use .+?\./
     */
    public function testConstructorThrowsErrorOnInvalidOptions($options)
    {
        new Packer($options);
    }

    public function provideInvalidOptionsData()
    {
        return [
            [PackOptions::FORCE_STR | PackOptions::FORCE_BIN],
            [PackOptions::FORCE_STR | PackOptions::FORCE_BIN | PackOptions::DETECT_STR_BIN],
            [PackOptions::FORCE_ARR | PackOptions::FORCE_MAP],
            [PackOptions::FORCE_ARR | PackOptions::FORCE_MAP | PackOptions::DETECT_ARR_MAP],
            [PackOptions::FORCE_STR | PackOptions::FORCE_BIN | PackOptions::DETECT_STR_BIN | PackOptions::FORCE_ARR | PackOptions::FORCE_MAP | PackOptions::DETECT_ARR_MAP],
        ];
    }

    /**
     * @dataProvider provideFloat32Data
     */
    public function testPackFloat32($raw, $packed)
    {
        $this->assertSame($packed, $this->packer->packFloat32($raw));
    }

    public function provideFloat32Data()
    {
        return [
            [0.0, "\xca"."\x00\x00\x00\x00"],
            [2.5, "\xca"."\x40\x20\x00\x00"],
        ];
    }

    public function testSetGetTransformers()
    {
        $coll = $this->getMockBuilder('MessagePack\TypeTransformer\Collection')->getMock();

        $this->assertNull($this->packer->getTransformers());
        $this->packer->setTransformers($coll);
        $this->assertSame($coll, $this->packer->getTransformers());
    }

    public function testPackCustomType()
    {
        $obj = new \stdClass();

        $transformer = $this->getTransformerMock(5);
        $transformer->expects($this->once())->method('transform')->willReturn(1);

        $coll = $this->getTransformerCollectionMock([$transformer]);
        $coll->expects($this->once())->method('match')->with($obj);
        $this->packer->setTransformers($coll);

        $this->assertSame("\xd4\x05\x01", $this->packer->pack($obj));
    }
}
