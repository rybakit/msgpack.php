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
     * @expectedException \MessagePack\Exception\PackingFailedException
     * @expectedExceptionMessage Unsupported type.
     */
    public function testPackUnsupportedType()
    {
        $this->packer->pack(tmpfile());
    }

    /**
     * @runInSeparateProcess
     * @expectedException \MessagePack\Exception\PackingFailedException
     * @expectedExceptionMessage The array is too big.
     */
    public function testPackArrThrowsException()
    {
        eval('namespace MessagePack;
            function count(array $arr) {
                return 0xffffffff + 1;
            }
        ');

        $this->packer->pack([4, 2]);
    }

    /**
     * @runInSeparateProcess
     * @expectedException \MessagePack\Exception\PackingFailedException
     * @expectedExceptionMessage The map is too big.
     */
    public function testPackMapThrowsException()
    {
        eval('namespace MessagePack;
            function count(array $map) {
                return 0xffffffff + 1;
            }
        ');

        $this->packer->pack(['foo' => 'bar']);
    }

    /**
     * @runInSeparateProcess
     * @expectedException \MessagePack\Exception\PackingFailedException
     * @expectedExceptionMessage The string is too big.
     */
    public function testPackStrThrowsException()
    {
        eval('namespace MessagePack;
            function strlen($str) {
                return 0xffffffff + 1;
            }
        ');

        $this->packer->pack('foobar');
    }

    /**
     * @runInSeparateProcess
     * @expectedException \MessagePack\Exception\PackingFailedException
     * @expectedExceptionMessage The binary string is too big.
     */
    public function testPackBinThrowsException()
    {
        eval('namespace MessagePack;
            function strlen($str) {
                return 0xffffffff + 1;
            }
        ');

        $this->packer->pack("\x80");
    }

    /**
     * @runInSeparateProcess
     * @expectedException \MessagePack\Exception\PackingFailedException
     * @expectedExceptionMessage The extension data is too big.
     */
    public function testPackExtThrowsException()
    {
        $ext = $this->getMockBuilder('MessagePack\Ext')
            ->disableOriginalConstructor()
            ->getMock();

        $ext->expects($this->once())->method('getType');
        $ext->expects($this->once())->method('getData');

        eval('namespace MessagePack;
            function strlen($str) {
                return 0xffffffff + 1;
            }
        ');

        $this->packer->pack($ext);
    }
}
