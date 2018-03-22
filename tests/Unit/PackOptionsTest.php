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
}
