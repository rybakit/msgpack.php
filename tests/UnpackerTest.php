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
        $this->unpacker = new Unpacker('');
    }

    /**
     * @dataProvider MessagePack\Tests\DataProvider::provideUnpackData
     */
    public function testUnpack($title, $raw, $packed)
    {
        $this->unpacker->append($packed);
        $this->assertEquals($raw, $this->unpacker->unpack());
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
}
