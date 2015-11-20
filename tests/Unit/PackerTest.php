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
}

/*
    public function testPackExtThrowsException()
    {
        $ext = $this->getMockBuilder('MessagePack\Ext')
            ->disableOriginalConstructor()
            ->getMock();

        $ext->expects($this->once())->method('getType')->willReturn(42);
        $ext->expects($this->once())->method('getData')->willReturn(str_repeat('x', 0xffffffff + 1));

        $this->packer->pack($ext);
    }

}

namespace MessagePack;

use MessagePack\Tests\PackerTest;

function strlen($string)
{
    return 0xffffffff + 1;
}
*/
