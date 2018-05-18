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

use MessagePack\Exception\InvalidOptionException;
use MessagePack\UnpackOptions;
use PHPUnit\Framework\TestCase;

final class UnpackOptionsTest extends TestCase
{
    /**
     * @dataProvider provideIsserData
     */
    public function testFromBitmask(string $isserName, bool $expectedResult, int $bitmask) : void
    {
        $options = UnpackOptions::fromBitmask($bitmask);

        self::assertSame($expectedResult, $options->{$isserName}());
    }

    public function provideIsserData() : array
    {
        return [
            ['isBigIntAsStrMode', true, 0],
            ['isBigIntAsStrMode', true, UnpackOptions::BIGINT_AS_STR],
            ['isBigIntAsStrMode', false, UnpackOptions::BIGINT_AS_GMP],
            ['isBigIntAsStrMode', false, UnpackOptions::BIGINT_AS_EXCEPTION],

            ['isBigIntAsGmpMode', false, 0],
            ['isBigIntAsGmpMode', false, UnpackOptions::BIGINT_AS_STR],
            ['isBigIntAsGmpMode', true, UnpackOptions::BIGINT_AS_GMP],
            ['isBigIntAsGmpMode', false, UnpackOptions::BIGINT_AS_EXCEPTION],

            ['isBigIntAsExceptionMode', false, 0],
            ['isBigIntAsExceptionMode', false, UnpackOptions::BIGINT_AS_STR],
            ['isBigIntAsExceptionMode', false, UnpackOptions::BIGINT_AS_GMP],
            ['isBigIntAsExceptionMode', true, UnpackOptions::BIGINT_AS_EXCEPTION],
        ];
    }

    /**
     * @dataProvider provideInvalidOptionsData
     */
    public function testFromBitmaskWithInvalidOptions(int $bitmask, string $errorMessage) : void
    {
        try {
            UnpackOptions::fromBitmask($bitmask);
        } catch (InvalidOptionException $e) {
            self::assertSame($e->getMessage(), $errorMessage);

            return;
        }

        self::fail(InvalidOptionException::class.' was not thrown.');
    }

    public function provideInvalidOptionsData() : iterable
    {
        yield [
            UnpackOptions::BIGINT_AS_GMP | UnpackOptions::BIGINT_AS_STR,
            'Invalid option bigint, use one of MessagePack\UnpackOptions::BIGINT_AS_STR, MessagePack\UnpackOptions::BIGINT_AS_GMP or MessagePack\UnpackOptions::BIGINT_AS_EXCEPTION.',
        ];
    }
}
