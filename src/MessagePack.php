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
use MessagePack\Exception\PackingFailedException;
use MessagePack\Exception\UnpackingFailedException;

final class MessagePack
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * @param mixed $value
     * @param PackOptions|int|null $options
     *
     * @throws InvalidOptionException
     * @throws PackingFailedException
     *
     * @return string
     */
    public static function pack($value, $options = null) : string
    {
        return (new Packer($options))->pack($value);
    }

    /**
     * @param string $data
     * @param UnpackOptions|int|null $options
     *
     * @throws InvalidOptionException
     * @throws UnpackingFailedException
     *
     * @return mixed
     */
    public static function unpack(string $data, $options = null)
    {
        return (new BufferUnpacker($data, $options))->unpack();
    }
}
