<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Exception;

class InsufficientDataException extends UnpackingFailedException
{
    public static function unexpectedLength(string $buffer, int $offset, int $expectedLength) : self
    {
        $actualLength = \strlen($buffer) - $offset;
        $message = "Not enough data to unpack: expected $expectedLength, got $actualLength.";

        return new self($message);
    }
}
