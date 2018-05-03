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

use MessagePack\Exception\InvalidOptionException;
use PHPUnit\Framework\TestCase;

final class InvalidOptionExceptionTest extends TestCase
{
    /**
     * @dataProvider provideOutOfRangeData
     */
    public function testOutOfRange(string $invalidOption, array $validOptions, string $message) : void
    {
        $exception = InvalidOptionException::outOfRange($invalidOption, $validOptions);

        self::assertSame($message, $exception->getMessage());
    }

    public function provideOutOfRangeData() : array
    {
        return [
            ['foobar', ['foo'], 'Invalid option foobar, use foo.'],
            ['foobar', ['foo', 'bar'], 'Invalid option foobar, use foo or bar.'],
            ['foobar', ['foo', 'bar', 'baz'], 'Invalid option foobar, use one of foo, bar or baz.'],
        ];
    }
}
