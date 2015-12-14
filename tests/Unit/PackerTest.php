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
            tmpfile(),
            new \stdClass(),
        ];
    }

    public function testPackCustomType()
    {
        $obj = new \stdClass();

        $transformer = $this->getMock('MessagePack\ExtDataTransformer');
        $transformer->expects($this->once())->method('supports')
            ->with($obj)
            ->willReturn(true);

        $transformer->expects($this->once())->method('getType')
            ->willReturn(5);

        $transformer->expects($this->once())->method('transform')
            ->willReturn(1);

        $this->packer->registerTransformer($transformer);

        $this->assertSame("\xd4\x05\x01", $this->packer->pack($obj));
    }
}
