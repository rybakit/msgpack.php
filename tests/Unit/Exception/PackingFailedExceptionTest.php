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
use PHPUnit\Framework\TestCase;

final class PackingFailedExceptionTest extends TestCase
{
    public function testConstructor() : void
    {
        $value = (object) ['foo' => 'bar'];
        $errorMessage = 'Error message';
        $prevException = new \Exception();

        $exception = new PackingFailedException($value, $errorMessage, $prevException);

        self::assertSame($value, $exception->getValue());
        self::assertSame($errorMessage, $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertSame($prevException, $exception->getPrevious());
    }
}
