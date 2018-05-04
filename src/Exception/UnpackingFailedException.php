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

class UnpackingFailedException extends \RuntimeException
{
    public static function unknownCode(int $code) : self
    {
        return new self(\sprintf('Unknown code: 0x%x.', $code));
    }

    public static function unexpectedCode(int $code, string $type) : self
    {
        return new self(\sprintf('Unexpected %s code: 0x%x.', $type, $code));
    }
}
