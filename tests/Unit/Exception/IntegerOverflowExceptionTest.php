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
use PHPUnit\Framework\TestCase;

final class IntegerOverflowExceptionTest extends TestCase
{
    public function testConstructor() : void
    {
        $value = -1;
        $errorCode = 42;
        $prevException = new \Exception();

        $exception = new IntegerOverflowException($value, $errorCode, $prevException);

        self::assertSame($value, $exception->getValue());
        self::assertSame('The value is too big: 18446744073709551615.', $exception->getMessage());
        self::assertSame($errorCode, $exception->getCode());
        self::assertSame($prevException, $exception->getPrevious());
    }
}
