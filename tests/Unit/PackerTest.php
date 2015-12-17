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

    public function testSetGetTransformers()
    {
        $coll = $this->getMock('MessagePack\TypeTransformer\Collection');

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
