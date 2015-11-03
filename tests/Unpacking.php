<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests;

trait Unpacking
{
    /**
     * @dataProvider MessagePack\Tests\DataProvider::provideUnpackData
     */
    public function testUnpack($title, $raw, $packed)
    {
        $this->assertEquals($raw, $this->unpack($packed));
    }

    /**
     * @expectedException \MessagePack\Exception\InsufficientDataException
     * @expectedExceptionMessage Not enough data to unpack: need 1, have 0.
     */
    public function testUnpackEmptyBuffer()
    {
        $this->unpack('');
    }

    /**
     * @expectedException \MessagePack\Exception\UnpackException
     * @expectedExceptionMessage Unknown code: 0xc1.
     */
    public function testUnknownCodeThrowsException()
    {
        $this->unpack("\xc1");
    }

    abstract protected function unpack($packed);
}
