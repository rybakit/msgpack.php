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
     * @dataProvider MessagePack\Tests\DataProvider::provideData
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
            [tmpfile()],
            [new \stdClass()],
        ];
    }

    /**
     * @dataProvider provideTypeDetectionModeData
     */
    public function testConstructorSetTypeDetectionMode($mode, $raw, $packed)
    {
        $this->assertSame($packed, (new Packer($mode))->pack($raw));
    }

    /**
     * @dataProvider provideTypeDetectionModeData
     */
    public function testSetTypeDetectionMode($mode, $raw, $packed)
    {
        $this->packer->setTypeDetectionMode($mode);
        $this->assertSame($packed, $this->packer->pack($raw));
    }

    public function provideTypeDetectionModeData()
    {
        return [
            [0, "\x80", "\xc4\x01\x80"],
            [0, 'a', "\xa1\x61"],
            [0, [1 => 2], "\x81\x01\x02"],
            [0, [0 => 1], "\x91\x01"],
            [Packer::FORCE_STR, "\x80", "\xa1\x80"],
            [Packer::FORCE_BIN, 'a', "\xc4\x01\x61"],
            [Packer::FORCE_ARR, [1 => 2], "\x91\x02"],
            [Packer::FORCE_MAP, [0 => 1], "\x81\x00\x01"],
            [Packer::FORCE_STR | Packer::FORCE_ARR, [1 => "\x80"], "\x91\xa1\x80"],
            [Packer::FORCE_STR | Packer::FORCE_MAP, [0 => "\x80"], "\x81\x00\xa1\x80"],
            [Packer::FORCE_BIN | Packer::FORCE_ARR, [1 => 'a'], "\x91\xc4\x01\x61"],
            [Packer::FORCE_BIN | Packer::FORCE_MAP, [0 => 'a'], "\x81\x00\xc4\x01\x61"],
        ];
    }

    /**
     * @dataProvider provideInvalidTypeDetectionModeData
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid type detection mode.
     */
    public function testSetTypeDetectionModeThrowsError($mode)
    {
        $this->packer->setTypeDetectionMode($mode);
    }

    /**
     * @dataProvider provideInvalidTypeDetectionModeData
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid type detection mode.
     */
    public function testConstructorThrowsErrorOnInvalidTypeDetectionMode($mode)
    {
        $this->packer->setTypeDetectionMode($mode);
    }

    public function provideInvalidTypeDetectionModeData()
    {
        return [
            [-1],
            [42],
            [Packer::FORCE_STR | Packer::FORCE_BIN],
            [Packer::FORCE_ARR | Packer::FORCE_MAP],
            [Packer::FORCE_STR | Packer::FORCE_BIN | Packer::FORCE_ARR | Packer::FORCE_MAP],
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
