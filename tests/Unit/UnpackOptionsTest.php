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

class UnpackOptionsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideIsserData
     */
    public function testFromBitmask($isserName, $expectedResult, $options)
    {
        $options = UnpackOptions::fromBitmask($options);

        self::assertSame($expectedResult, $options->{$isserName}());
    }

    public function provideIsserData()
    {
        return [
            ['isBigIntAsStrMode', true, null],
            ['isBigIntAsStrMode', true, UnpackOptions::BIGINT_AS_STR],
            ['isBigIntAsStrMode', false, UnpackOptions::BIGINT_AS_GMP],
            ['isBigIntAsStrMode', false, UnpackOptions::BIGINT_AS_EXCEPTION],

            ['isBigIntAsGmpMode', false, null],
            ['isBigIntAsGmpMode', false, UnpackOptions::BIGINT_AS_STR],
            ['isBigIntAsGmpMode', true, UnpackOptions::BIGINT_AS_GMP],
            ['isBigIntAsGmpMode', false, UnpackOptions::BIGINT_AS_EXCEPTION],
        ];
    }

    /**
     * @dataProvider provideInvalidOptionsData
     */
    public function testFromBitmaskWithInvalidOptions($options, $errorMessage)
    {
        try {
            UnpackOptions::fromBitmask($options);
        } catch (InvalidOptionException $e) {
            self::assertSame($e->getMessage(), $errorMessage);

            return;
        }

        self::fail();
    }

    public function provideInvalidOptionsData()
    {
        return [
            [
                UnpackOptions::BIGINT_AS_GMP | UnpackOptions::BIGINT_AS_STR,
                'Invalid option bigint, use one of MessagePack\UnpackOptions::BIGINT_AS_STR, MessagePack\UnpackOptions::BIGINT_AS_GMP or MessagePack\UnpackOptions::BIGINT_AS_EXCEPTION.',
            ]
        ];
    }
}
