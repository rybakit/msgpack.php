<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\Exception;

use MessagePack\Exception\PackingFailedException;

class PackingFailedExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $value = (object) ['foo' => 'bar'];
        $errorMessage = 'Error message';
        $errorCode = 42;
        $prevException = new \Exception();

        $exception = new PackingFailedException($value, $errorMessage, $errorCode, $prevException);

        $this->assertSame($value, $exception->getValue());
        $this->assertSame($errorMessage, $exception->getMessage());
        $this->assertSame($errorCode, $exception->getCode());
        $this->assertSame($prevException, $exception->getPrevious());
    }
}
