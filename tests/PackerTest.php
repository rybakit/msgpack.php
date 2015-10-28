<?php

namespace MessagePack\Tests;

use MessagePack\Packer;

class PackerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Packer
     */
    private $packer;

    protected function setUp()
    {
        $this->packer = new Packer();
    }

    /**
     * @dataProvider MessagePack\Tests\DataProvider::providePackData
     */
    public function testPack($title, $raw, $packed)
    {
        $this->assertEquals($packed, $this->packer->pack($raw));
    }

    public function testPackArrayToMessagePackArray()
    {
        $this->assertSame("\x91\x05", $this->packer->pack([5]));
        $this->assertSame("\x91\x05", $this->packer->pack([5], Packer::FORCE_ARR));
    }

    public function testPackComplexArrayToMessagePackArray()
    {
        $this->assertSame("\x92\x05\x91\x07", $this->packer->pack([5, [7]]));
        $this->assertSame("\x92\x05\x91\x07", $this->packer->pack([5, [7]], Packer::FORCE_ARR));
    }

    public function testPackEmptyArrayToMessagePackArray()
    {
        $this->assertSame("\x90", $this->packer->pack([]));
    }

    public function testPackArrayToMessagePackMap()
    {
        $this->assertSame("\x81\x00\x05", $this->packer->pack([5], Packer::FORCE_MAP));
    }

    public function testPackComplexArrayToMessagePackMap()
    {
        $this->assertSame("\x82\x00\x05\x01\x81\x00\x07", $this->packer->pack([5, [7]], Packer::FORCE_MAP));
    }

    public function testPackEmptyArrayToMessagePackMap()
    {
        $this->assertSame("\x80", $this->packer->pack([], Packer::FORCE_MAP));
    }

    public function testPackStringToMessagePackString()
    {
        $this->assertSame("\xa3\x66\x6f\x6f", $this->packer->pack('foo', Packer::FORCE_UTF8));
    }

    public function testPackStringToMessagePackStringAuto()
    {
        $this->assertSame("\xa3\x66\x6f\x6f", $this->packer->pack('foo'));
    }

    public function testPackStringToMessagePackBin()
    {
        $this->assertSame("\xc4\x01"."\x80", $this->packer->pack("\x80", Packer::FORCE_BIN));
    }

    public function testPackStringToMessagePackBinAuto()
    {
        $this->assertSame("\xc4\x01"."\x80", $this->packer->pack("\x80"));
    }
}
