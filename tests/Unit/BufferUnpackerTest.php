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

use MessagePack\BufferUnpacker;
use MessagePack\Exception\InsufficientDataException;

class BufferUnpackerTest extends \PHPUnit_Framework_TestCase
{
    use TransformerUtils;

    /**
     * @var BufferUnpacker
     */
    private $unpacker;

    protected function setUp()
    {
        $this->unpacker = new BufferUnpacker();
    }

    /**
     * @dataProvider MessagePack\Tests\DataProvider::provideUnpackData
     */
    public function testUnpack($title, $raw, $packed)
    {
        $this->unpacker->reset($packed);
        $isOrHasObject = is_object($raw) || is_array($raw);

        $isOrHasObject
            ? $this->assertEquals($raw, $this->unpacker->unpack())
            : $this->assertSame($raw, $this->unpacker->unpack());
    }

    /**
     * @expectedException \MessagePack\Exception\InsufficientDataException
     * @expectedExceptionMessage Not enough data to unpack: need 1, have 0.
     */
    public function testUnpackEmptyBuffer()
    {
        $this->unpacker->unpack();
    }

    /**
     * @expectedException \MessagePack\Exception\UnpackingFailedException
     * @expectedExceptionMessage Unknown code: 0xc1.
     */
    public function testUnknownCodeThrowsException()
    {
        $this->unpacker->reset("\xc1")->unpack();
    }

    /**
     * @expectedException \MessagePack\Exception\IntegerOverflowException
     * @expectedExceptionMessage The value is too big: 18446744073709551615.
     */
    public function testUnpackUint64ThrowsException()
    {
        $this->unpacker->reset("\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff");

        $this->unpacker->unpack();
    }

    public function testUnpackUint64AsString()
    {
        $unpacker = new BufferUnpacker(BufferUnpacker::INT_AS_STR);
        $unpacker->reset("\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff");

        $this->assertSame('18446744073709551615', $unpacker->unpack());
    }

    /**
     * @requires extension gmp
     */
    public function testUnpackUint64AsGmp()
    {
        $unpacker = new BufferUnpacker(BufferUnpacker::INT_AS_GMP);
        $unpacker->reset("\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff");
        $uint64 = $unpacker->unpack();

        if (PHP_VERSION_ID < 50600) {
            $this->assertInternalType('resource', $uint64);
        } else {
            $this->assertInstanceOf('GMP', $uint64);
        }

        $this->assertSame('18446744073709551615', gmp_strval($uint64));
    }

    /**
     * @expectedException \MessagePack\Exception\InsufficientDataException
     * @expectedExceptionMessage Not enough data to unpack: need 1, have 0.
     */
    public function testReset()
    {
        $this->unpacker->append("\xc3")->reset()->unpack();
    }

    public function testResetWithBuffer()
    {
        $this->unpacker->append("\xc2")->reset("\xc3");

        $this->assertTrue($this->unpacker->unpack());
    }

    public function testTryUnpack()
    {
        $foo = [1, 2];
        $bar = 'bar';
        $packed = "\x92\x01\x02\xa3\x62\x61\x72";

        $this->unpacker->append($packed[0]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[1]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[2]);
        $this->assertSame([$foo], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[3]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[4].$packed[5]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[6]);
        $this->assertSame([$bar], $this->unpacker->tryUnpack());
    }

    public function testTryUnpackReturnsAllUnpackedData()
    {
        $foo = [1, 2];
        $bar = 'bar';
        $packed = "\x92\x01\x02\xa3\x62\x61\x72";

        $this->unpacker->append($packed);
        $this->assertSame([$foo, $bar], $this->unpacker->tryUnpack());
    }

    public function testTryUnpackTruncatesBuffer()
    {
        $this->unpacker->append("\xc3");

        $this->assertSame([true], $this->unpacker->tryUnpack());

        try {
            $this->unpacker->unpack();
        } catch (InsufficientDataException $e) {
            $this->assertSame('Not enough data to unpack: need 1, have 0.', $e->getMessage());

            return;
        }

        $this->fail('Buffer was not truncated.');
    }

    public function testSetGetIntOverflowMode()
    {
        $this->assertSame(BufferUnpacker::INT_AS_EXCEPTION, $this->unpacker->getIntOverflowMode());

        $this->unpacker->setIntOverflowMode(BufferUnpacker::INT_AS_STR);
        $this->assertSame(BufferUnpacker::INT_AS_STR, $this->unpacker->getIntOverflowMode());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid integer overflow mode: 42.
     */
    public function testSetIntOverflowModeThrowsException()
    {
        $this->unpacker->setIntOverflowMode(42);
    }

    /**
     * @expectedException \PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessage Illegal offset type
     */
    public function testBadKeyTypeThrowsWarning()
    {
        $this->unpacker->reset("\x81\x82\x00\x01\x01\x02\x00"); // [[1, 2] => 0]

        $this->unpacker->unpack();
    }

    public function testBadKeyTypeIsIgnored()
    {
        $this->unpacker->reset("\x82\x82\x00\x01\x01\x02\x00\x04\x02"); // [[1, 2] => 0, 4 => 2]
        $raw = @$this->unpacker->unpack();

        $this->assertSame([4 => 2], $raw);
    }

    public function testSetGetTransformers()
    {
        $coll = $this->getTransformerCollectionMock();

        $this->assertNull($this->unpacker->getTransformers());
        $this->unpacker->setTransformers($coll);
        $this->assertSame($coll, $this->unpacker->getTransformers());
    }

    public function testUnpackCustomType()
    {
        $obj = new \stdClass();

        $transformer = $this->getTransformerMock(5);
        $transformer->expects($this->once())->method('reverseTransform')->willReturn($obj);

        $coll = $this->getTransformerCollectionMock([$transformer]);
        $coll->expects($this->once())->method('find')->with(5);
        $this->unpacker->setTransformers($coll);

        $this->assertSame($obj, $this->unpacker->reset("\xd4\x05\x01")->unpack());
    }
}
