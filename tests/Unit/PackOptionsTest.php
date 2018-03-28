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
use MessagePack\PackOptions;

class PackOptionsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideIsserData
     */
    public function testFromBitmask($isserName, $expectedResult, $options)
    {
        $options = PackOptions::fromBitmask($options);

        self::assertSame($expectedResult, $options->{$isserName}());
    }

    public function provideIsserData()
    {
        return [
            ['isDetectStrBinMode', true, null],
            ['isDetectStrBinMode', false, PackOptions::FORCE_STR],
            ['isDetectStrBinMode', false, PackOptions::FORCE_BIN],
            ['isDetectStrBinMode', true, PackOptions::DETECT_STR_BIN],

            ['isForceStrMode', false, null],
            ['isForceStrMode', true, PackOptions::FORCE_STR],
            ['isForceStrMode', false, PackOptions::FORCE_BIN],
            ['isForceStrMode', false, PackOptions::DETECT_STR_BIN],

            ['isDetectArrMapMode', true, null],
            ['isDetectArrMapMode', false, PackOptions::FORCE_ARR],
            ['isDetectArrMapMode', false, PackOptions::FORCE_MAP],
            ['isDetectArrMapMode', true, PackOptions::DETECT_STR_BIN],

            ['isForceArrMode', false, null],
            ['isForceArrMode', true, PackOptions::FORCE_ARR],
            ['isForceArrMode', false, PackOptions::FORCE_MAP],
            ['isForceArrMode', false, PackOptions::DETECT_ARR_MAP],

            ['isForceFloat32Mode', false, null],
            ['isForceFloat32Mode', true, PackOptions::FORCE_FLOAT32],
            ['isForceFloat32Mode', false, PackOptions::FORCE_FLOAT64],
        ];
    }

    /**
     * @dataProvider provideInvalidOptionsData
     */
    public function testFromBitmaskWithInvalidOptions($options, $errorMessage)
    {
        try {
            PackOptions::fromBitmask($options);
        } catch (InvalidOptionException $e) {
            self::assertSame($e->getMessage(), $errorMessage);

            return;
        }

        self::fail('InvalidOptionException was not thrown.');
    }

    public function provideInvalidOptionsData()
    {
        return [
            [
                PackOptions::FORCE_STR | PackOptions::FORCE_BIN,
                'Invalid option str/bin, use one of MessagePack\PackOptions::FORCE_STR, MessagePack\PackOptions::FORCE_BIN or MessagePack\PackOptions::DETECT_STR_BIN.',
            ],
            [
                PackOptions::FORCE_ARR | PackOptions::FORCE_MAP,
                'Invalid option arr/map, use one of MessagePack\PackOptions::FORCE_ARR, MessagePack\PackOptions::FORCE_MAP or MessagePack\PackOptions::DETECT_ARR_MAP.',
            ],
            [
                PackOptions::FORCE_FLOAT32 | PackOptions::FORCE_FLOAT64,
                'Invalid option float, use MessagePack\PackOptions::FORCE_FLOAT32 or MessagePack\PackOptions::FORCE_FLOAT64.',
            ],
        ];
    }
}
