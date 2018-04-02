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
use MessagePack\UnpackOptions;

class BufferUnpackerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var BufferUnpacker
     */
    private $unpacker;

    protected function setUp()
    {
        $this->unpacker = new BufferUnpacker();
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideUnpackData
     */
    public function testUnpack($title, $raw, $packed)
    {
        $this->unpacker->reset($packed);
        $isOrHasObject = \is_object($raw) || \is_array($raw);

        $isOrHasObject
            ? self::assertEquals($raw, $this->unpacker->unpack())
            : self::assertSame($raw, $this->unpacker->unpack());
    }

    /**
     * @dataProvider provideInsufficientData
     */
    public function testUnpackInsufficientData($data, $expectedLength, $actualLength)
    {
        try {
            $this->unpacker->reset($data)->unpack();
        } catch (InsufficientDataException $e) {
            self::assertSame("Not enough data to unpack: expected $expectedLength, got $actualLength.", $e->getMessage());

            return;
        }

        self::fail('InsufficientDataException was not thrown.');
    }

    public function provideInsufficientData()
    {
        return [
            'str'       => ['', 1, 0],
            'uint8'     => ["\xcc", 1, 0],
            'uint16'    => ["\xcd", 2, 0],
            'uint32'    => ["\xce", 4, 0],
            'uint64'    => ["\xcf", 8, 0],
            'in8'       => ["\xd0", 1, 0],
            'int16'     => ["\xd1", 2, 0],
            'int32'     => ["\xd2", 4, 0],
            'int64'     => ["\xd3", 8, 0],
            'float32'   => ["\xca", 4, 0],
            'float64'   => ["\xcb", 8, 0],
            'fixext1'   => ["\xd4", 1, 0],
            'fixext2'   => ["\xd5", 2, 0],
            'fixext4'   => ["\xd6", 4, 0],
            'fixext8'   => ["\xd7", 8, 0],
            'fixext16'  => ["\xd8", 16, 0],
            'ext8'      => ["\xc7", 1, 0],
            'ext16'     => ["\xc8", 2, 0],
            'ext32'     => ["\xc9", 4, 0],
        ];
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
    public function testUnpackBigIntAsException()
    {
        $unpacker = new BufferUnpacker(
            "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff",
            UnpackOptions::BIGINT_AS_EXCEPTION
        );

        $unpacker->unpack();
    }

    public function testUnpackBigIntAsString()
    {
        $unpacker = new BufferUnpacker(
            "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff",
            UnpackOptions::BIGINT_AS_STR
        );

        self::assertSame('18446744073709551615', $unpacker->unpack());
    }

    public function testUnpackBigIntDefaultModeString()
    {
        $unpacker = new BufferUnpacker("\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff");

        self::assertSame('18446744073709551615', $unpacker->unpack());
    }

    /**
     * @requires extension gmp
     */
    public function testUnpackBigIntAsGmp()
    {
        $unpacker = new BufferUnpacker(
            "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff",
            UnpackOptions::BIGINT_AS_GMP
        );

        $uint64 = $unpacker->unpack();

        if (PHP_VERSION_ID < 50600) {
            self::assertInternalType('resource', $uint64);
        } else {
            self::assertInstanceOf('GMP', $uint64);
        }

        self::assertSame('18446744073709551615', \gmp_strval($uint64));
    }

    /**
     * @expectedException \MessagePack\Exception\InsufficientDataException
     * @expectedExceptionMessage Not enough data to unpack: expected 1, got 0.
     */
    public function testReset()
    {
        $this->unpacker->append("\xc3")->reset()->unpack();
    }

    public function testResetWithBuffer()
    {
        $this->unpacker->append("\xc2")->reset("\xc3");

        self::assertTrue($this->unpacker->unpack());
    }

    public function testSkip()
    {
        $this->unpacker->append("\xc2\xc2\xc3")->skip(2);

        self::assertTrue($this->unpacker->unpack());
    }

    public function testTryUnpack()
    {
        $foo = [1, 2];
        $bar = 'bar';
        $packed = "\x92\x01\x02\xa3\x62\x61\x72";

        $this->unpacker->append($packed[0]);
        self::assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[1]);
        self::assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[2].$packed[3]);
        self::assertSame([$foo], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[4].$packed[5]);
        self::assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[6]);
        self::assertSame([$bar], $this->unpacker->tryUnpack());
    }

    public function testTryUnpackReturnsAllUnpackedData()
    {
        $foo = [1, 2];
        $bar = 'bar';
        $packed = "\x92\x01\x02\xa3\x62\x61\x72";

        $this->unpacker->append($packed);
        self::assertSame([$foo, $bar], $this->unpacker->tryUnpack());
    }

    public function testTryUnpackTruncatesBuffer()
    {
        $this->unpacker->append("\xc3");

        self::assertSame([true], $this->unpacker->tryUnpack());

        try {
            $this->unpacker->unpack();
        } catch (InsufficientDataException $e) {
            self::assertSame('Not enough data to unpack: expected 1, got 0.', $e->getMessage());

            return;
        }

        self::fail('Buffer was not truncated.');
    }

    /**
     * @dataProvider provideInvalidOptionsData
     * @expectedException \MessagePack\Exception\InvalidOptionException
     * @expectedExceptionMessageRegExp /Invalid option .+?, use .+?\./
     */
    public function testConstructorThrowsErrorOnInvalidOptions($options)
    {
        new BufferUnpacker('', $options);
    }

    public function provideInvalidOptionsData()
    {
        return [
            [UnpackOptions::BIGINT_AS_STR | UnpackOptions::BIGINT_AS_GMP],
            [UnpackOptions::BIGINT_AS_STR | UnpackOptions::BIGINT_AS_EXCEPTION],
            [UnpackOptions::BIGINT_AS_GMP | UnpackOptions::BIGINT_AS_EXCEPTION],
            [UnpackOptions::BIGINT_AS_STR | UnpackOptions::BIGINT_AS_GMP | UnpackOptions::BIGINT_AS_EXCEPTION],
        ];
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

        self::assertSame([4 => 2], $raw);
    }

    public function testUnpackCustomType()
    {
        $obj = new \stdClass();
        $type = 5;

        $transformer = $this->getMockBuilder('MessagePack\TypeTransformer\Extension')->getMock();
        $transformer->expects(self::any())->method('getType')->willReturn($type);
        $transformer->expects(self::once())->method('unpack')
            ->with($this->unpacker, 1)
            ->willReturn($obj);

        $this->unpacker->registerTransformer($transformer);

        self::assertSame($obj, $this->unpacker->reset("\xd4\x05\x01")->unpack());
    }
}
