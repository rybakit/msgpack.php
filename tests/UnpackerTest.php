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

use MessagePack\Unpacker;

class UnpackerTest extends \PHPUnit_Framework_TestCase
{
    use Unpacking;

    /**
     * @var Unpacker
     */
    private $unpacker;

    protected function setUp()
    {
        $this->unpacker = new Unpacker();
    }

    protected function unpack($packed)
    {
        return $this->unpacker->unpack($packed);
    }
}
