<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack;

use MessagePack\Exception\InvalidOptionException;

final class PackOptions
{
    const FORCE_STR         = 0b00000001;
    const FORCE_BIN         = 0b00000010;
    const DETECT_STR_BIN    = 0b00000100;
    const FORCE_ARR         = 0b00001000;
    const FORCE_MAP         = 0b00010000;
    const DETECT_ARR_MAP    = 0b00100000;
    const FORCE_FLOAT32     = 0b01000000;
    const FORCE_FLOAT64     = 0b10000000;

    private $strBinMode;
    private $arrMapMode;
    private $floatMode;

    private function __construct()
    {
    }

    public static function fromBitmask($options)
    {
        $self = new self();

        $self->strBinMode = self::getSingleOption('str/bin', $options,
            self::FORCE_STR |
            self::FORCE_BIN |
            self::DETECT_STR_BIN
        ) ?: self::DETECT_STR_BIN;

        $self->arrMapMode = self::getSingleOption('arr/map', $options,
            self::FORCE_ARR |
            self::FORCE_MAP |
            self::DETECT_ARR_MAP
        ) ?: self::DETECT_ARR_MAP;

        $self->floatMode = self::getSingleOption('float', $options,
            self::FORCE_FLOAT32 |
            self::FORCE_FLOAT64
        ) ?: self::FORCE_FLOAT64;

        return $self;
    }

    public function isDetectStrBinMode()
    {
        return self::DETECT_STR_BIN === $this->strBinMode;
    }

    public function isForceStrMode()
    {
        return self::FORCE_STR === $this->strBinMode;
    }

    public function isDetectArrMapMode()
    {
        return self::DETECT_ARR_MAP === $this->arrMapMode;
    }

    public function isForceArrMode()
    {
        return self::FORCE_ARR === $this->arrMapMode;
    }

    public function isForceFloat32Mode()
    {
        return self::FORCE_FLOAT32 === $this->floatMode;
    }

    private static function getSingleOption($name, $options, $mask)
    {
        $option = $options & $mask;
        if ($option === ($option & -$option)) {
            return $option;
        }

        static $map = [
            self::FORCE_STR => 'FORCE_STR',
            self::FORCE_BIN => 'FORCE_BIN',
            self::DETECT_STR_BIN => 'DETECT_STR_BIN',
            self::FORCE_ARR => 'FORCE_ARR',
            self::FORCE_MAP => 'FORCE_MAP',
            self::DETECT_ARR_MAP => 'DETECT_ARR_MAP',
            self::FORCE_FLOAT32 => 'FORCE_FLOAT32',
            self::FORCE_FLOAT64 => 'FORCE_FLOAT64',
        ];

        $validOptions = [];
        for ($i = $mask & -$mask; $i <= $mask; $i <<= 1) {
            $validOptions[] = __CLASS__.'::'.$map[$i];
        }

        throw InvalidOptionException::fromValidOptions($name, $validOptions);
    }
}
