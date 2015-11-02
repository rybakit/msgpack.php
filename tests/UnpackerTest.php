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

    public function testFlush()
    {
        $this->unpacker->append("\x80");
        $this->assertSame(1, $this->unpacker->getBufferLength());
        $this->unpacker->flush();
        $this->assertSame(0, $this->unpacker->getBufferLength());
    }

    public function getBufferLength()
    {
        $this->assertSame(0, $this->unpacker->getBufferLength());
        $this->unpacker->append("\x80");
        $this->assertSame(1, $this->unpacker->getBufferLength());
    }

    public function testUnpackEmptyMapToArray()
    {
        $this->unpacker->append("\x80");
        $this->assertSame([], $this->unpacker->unpack());
    }

    public function testTryUnpack()
    {
        $raw = ['foo', 42];
        $packed = "\xa3\x66\x6f\x6f\x2a";

        $this->unpacker->append($packed[0]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[1]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append(substr($packed, 2));
        $this->assertSame($raw, $this->unpacker->tryUnpack());
    }

    public function testTryUnpackTruncatesBuffer()
    {
        $this->unpacker->append("\xc3");
        $this->assertSame(1, $this->unpacker->getBufferLength());

        $this->assertSame([true], $this->unpacker->tryUnpack());

        $this->assertSame(0, $this->unpacker->getBufferLength());

        $this->unpacker->append("\xc3");
        $this->assertTrue($this->unpacker->unpack());
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
