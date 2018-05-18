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
use PHPUnit\Framework\TestCase;

final class PackOptionsTest extends TestCase
{
    /**
     * @dataProvider provideIsserData
     */
    public function testFromBitmask(string $isserName, bool $expectedResult, int $bitmask) : void
    {
        $options = PackOptions::fromBitmask($bitmask);

        self::assertSame($expectedResult, $options->{$isserName}());
    }

    public function provideIsserData() : array
    {
        return [
            ['isDetectStrBinMode', true, 0],
            ['isDetectStrBinMode', false, PackOptions::FORCE_STR],
            ['isDetectStrBinMode', false, PackOptions::FORCE_BIN],
            ['isDetectStrBinMode', true, PackOptions::DETECT_STR_BIN],

            ['isForceStrMode', false, 0],
            ['isForceStrMode', true, PackOptions::FORCE_STR],
            ['isForceStrMode', false, PackOptions::FORCE_BIN],
            ['isForceStrMode', false, PackOptions::DETECT_STR_BIN],

            ['isForceBinMode', false, 0],
            ['isForceBinMode', false, PackOptions::FORCE_STR],
            ['isForceBinMode', true, PackOptions::FORCE_BIN],
            ['isForceBinMode', false, PackOptions::DETECT_STR_BIN],

            ['isDetectArrMapMode', true, 0],
            ['isDetectArrMapMode', false, PackOptions::FORCE_ARR],
            ['isDetectArrMapMode', false, PackOptions::FORCE_MAP],
            ['isDetectArrMapMode', true, PackOptions::DETECT_STR_BIN],

            ['isForceArrMode', false, 0],
            ['isForceArrMode', true, PackOptions::FORCE_ARR],
            ['isForceArrMode', false, PackOptions::FORCE_MAP],
            ['isForceArrMode', false, PackOptions::DETECT_ARR_MAP],

            ['isForceMapMode', false, 0],
            ['isForceMapMode', false, PackOptions::FORCE_ARR],
            ['isForceMapMode', true, PackOptions::FORCE_MAP],
            ['isForceMapMode', false, PackOptions::DETECT_ARR_MAP],

            ['isForceFloat32Mode', false, 0],
            ['isForceFloat32Mode', true, PackOptions::FORCE_FLOAT32],
            ['isForceFloat32Mode', false, PackOptions::FORCE_FLOAT64],
        ];
    }

    /**
     * @dataProvider provideInvalidOptionsData
     */
    public function testFromBitmaskWithInvalidOptions(int $bitmask, string $errorMessage) : void
    {
        try {
            PackOptions::fromBitmask($bitmask);
        } catch (InvalidOptionException $e) {
            self::assertSame($e->getMessage(), $errorMessage);

            return;
        }

        self::fail(InvalidOptionException::class.' was not thrown.');
    }

    public function provideInvalidOptionsData() : iterable
    {
        yield [
            PackOptions::FORCE_STR | PackOptions::FORCE_BIN,
            'Invalid option str/bin, use one of MessagePack\PackOptions::FORCE_STR, MessagePack\PackOptions::FORCE_BIN or MessagePack\PackOptions::DETECT_STR_BIN.',
        ];
        yield [
            PackOptions::FORCE_ARR | PackOptions::FORCE_MAP,
            'Invalid option arr/map, use one of MessagePack\PackOptions::FORCE_ARR, MessagePack\PackOptions::FORCE_MAP or MessagePack\PackOptions::DETECT_ARR_MAP.',
        ];
        yield [
            PackOptions::FORCE_FLOAT32 | PackOptions::FORCE_FLOAT64,
            'Invalid option float, use MessagePack\PackOptions::FORCE_FLOAT32 or MessagePack\PackOptions::FORCE_FLOAT64.',
        ];
    }
}
