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
            ['isBigIntAsFloatMode', true, 0],
            ['isBigIntAsFloatMode', false, UnpackOptions::BIGINT_AS_STR],
            ['isBigIntAsFloatMode', false, UnpackOptions::BIGINT_AS_GMP],
            ['isBigIntAsFloatMode', true, UnpackOptions::BIGINT_AS_FLOAT],

            ['isBigIntAsStrMode', false, 0],
            ['isBigIntAsStrMode', true, UnpackOptions::BIGINT_AS_STR],
            ['isBigIntAsStrMode', false, UnpackOptions::BIGINT_AS_GMP],
            ['isBigIntAsStrMode', false, UnpackOptions::BIGINT_AS_FLOAT],

            ['isBigIntAsGmpMode', false, 0],
            ['isBigIntAsGmpMode', false, UnpackOptions::BIGINT_AS_STR],
            ['isBigIntAsGmpMode', true, UnpackOptions::BIGINT_AS_GMP],
            ['isBigIntAsGmpMode', false, UnpackOptions::BIGINT_AS_FLOAT],
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
            'Invalid option bigint, use one of MessagePack\UnpackOptions::BIGINT_AS_FLOAT, MessagePack\UnpackOptions::BIGINT_AS_STR or MessagePack\UnpackOptions::BIGINT_AS_GMP.',
        ];
    }
}
