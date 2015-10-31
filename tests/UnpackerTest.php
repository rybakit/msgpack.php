<?php

namespace MessagePack\Tests;

use MessagePack\Unpacker;

class UnpackerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Unpacker
     */
    private $unpacker;

    protected function setUp()
    {
        $this->unpacker = new Unpacker();
    }

    /**
     * @dataProvider MessagePack\Tests\DataProvider::provideUnpackData
     */
    public function testUnpack($title, $raw, $packed)
    {
        $this->unpacker->append($packed);
        $this->assertEquals($raw, $this->unpacker->unpack());
    }

    /**
     * @expectedException \MessagePack\Exception\InsufficientDataException
     * @expectedExceptionMessage Not enough data to unpack: need 1, have 0.
     */
    public function testConstructorWithoutArgument()
    {
        (new Unpacker())->unpack();
    }

    public function testConstructorWithArgument()
    {
        $this->assertSame(true, (new Unpacker("\xc3"))->unpack());
    }

    /**
     * @expectedException \MessagePack\Exception\InsufficientDataException
     * @expectedExceptionMessage Not enough data to unpack: need 1, have 0.
     */
    public function testFlush()
    {
        (new Unpacker("\xc3"))->flush()->unpack();
    }

    public function testUnpackEmptyMapToArray()
    {
        $this->unpacker->append("\x80");
        $this->assertSame([], $this->unpacker->unpack());
    }

    public function testUnpackStream()
    {
        $raw = ['foo', 42];
        $packed = hex2bin('a3666f6f2a');

        $this->unpacker->append($packed[0]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[1]);
        $this->unpacker->append(substr($packed, 2));
        $this->assertSame($raw, $this->unpacker->tryUnpack());
    }

    /**
     * @expectedException \MessagePack\Exception\UnpackException
     * @expectedExceptionMessage Unknown code: 0xc1.
     */
    public function testUnknownCodeThrowsException()
    {
        $this->unpacker->append("\xc1")->unpack();
    }
}
