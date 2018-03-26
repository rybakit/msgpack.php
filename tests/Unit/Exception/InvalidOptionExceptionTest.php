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

class InvalidOptionExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideFromValidOptionsData
     */
    public function testFromValidOptions($invalidOption, array $validOptions, $message)
    {
        $exception = InvalidOptionException::fromValidOptions($invalidOption, $validOptions);

        self::assertSame($message, $exception->getMessage());
    }

    public function provideFromValidOptionsData()
    {
        return [
            ['foobar', ['foo'], 'Invalid option foobar, use foo.'],
            ['foobar', ['foo', 'bar'], 'Invalid option foobar, use foo or bar.'],
            ['foobar', ['foo', 'bar', 'baz'], 'Invalid option foobar, use one of foo, bar or baz.'],
        ];
    }
}
