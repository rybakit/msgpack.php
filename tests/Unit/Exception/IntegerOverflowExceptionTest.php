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

use MessagePack\Exception\IntegerOverflowException;

class IntegerOverflowExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $value = -1;
        $errorCode = 42;
        $prevException = new \Exception();

        $exception = new IntegerOverflowException($value, $errorCode, $prevException);

        $this->assertSame($value, $exception->getValue());
        $this->assertSame('The value is too big: 18446744073709551615.', $exception->getMessage());
        $this->assertSame($errorCode, $exception->getCode());
        $this->assertSame($prevException, $exception->getPrevious());
    }
}
