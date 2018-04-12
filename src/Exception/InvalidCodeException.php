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

class InvalidCodeException extends UnpackingFailedException
{
    public static function fromUnknownCode($code)
    {
        return new self(\sprintf('Unknown code: 0x%x.', $code), $code);
    }

    public static function fromExpectedType($type, $code)
    {
        return new self(\sprintf('Invalid %s code: 0x%x.', $type, $code), $code);
    }
}
