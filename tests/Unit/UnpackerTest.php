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

use MessagePack\Unpacker;

class UnpackerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Unpacker
     */
    private $unpacker;

    /**
     * @var \MessagePack\BufferUnpacker|\PHPUnit_Framework_MockObject_MockObject
     */
    private $bufferUnpacker;

    protected function setUp()
    {
        $this->bufferUnpacker = $this->getMock('MessagePack\BufferUnpacker');
        $this->unpacker = new Unpacker($this->bufferUnpacker);
    }

    public function testUnpack()
    {
        $this->bufferUnpacker->expects($this->once())->method('reset')
            ->with('foo')
            ->willReturn($this->bufferUnpacker);

        $this->bufferUnpacker->expects($this->once())->method('unpack')
            ->willReturn('bar');

        $this->assertSame('bar', $this->unpacker->unpack('foo'));
    }
}
