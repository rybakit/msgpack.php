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

final class UnpackOptions
{
    const BIGINT_AS_STR       = 0b001;
    const BIGINT_AS_GMP       = 0b010;
    const BIGINT_AS_EXCEPTION = 0b100;

    private $bigIntMode;

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    public static function fromBitmask($options)
    {
        $self = new self();

        $self->bigIntMode = self::getSingleOption('bigint', $options,
            self::BIGINT_AS_STR |
            self::BIGINT_AS_GMP |
            self::BIGINT_AS_EXCEPTION
        ) ?: self::BIGINT_AS_STR;

        return $self;
    }

    public function isBigIntAsStrMode()
    {
        return self::BIGINT_AS_STR === $this->bigIntMode;
    }

    public function isBigIntAsGmpMode()
    {
        return self::BIGINT_AS_GMP === $this->bigIntMode;
    }

    private static function getSingleOption($name, $options, $mask)
    {
        $option = $options & $mask;
        if ($option === ($option & -$option)) {
            return $option;
        }

        static $map = [
            self::BIGINT_AS_STR => 'BIGINT_AS_STR',
            self::BIGINT_AS_GMP => 'BIGINT_AS_GMP',
            self::BIGINT_AS_EXCEPTION => 'BIGINT_AS_EXCEPTION',
        ];

        $validOptions = [];
        for ($i = $mask & -$mask; $i <= $mask; $i <<= 1) {
            $validOptions[] = __CLASS__.'::'.$map[$i];
        }

        throw InvalidOptionException::fromValidOptions($name, $validOptions);
    }
}
