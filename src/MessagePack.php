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
     * @return string
     *
     * @throws \MessagePack\Exception\InvalidOptionException
     * @throws \MessagePack\Exception\PackingFailedException
     */
    public static function pack($value, $options = null)
    {
        return (new Packer($options))->pack($value);
    }

    /**
     * @param string $data
     * @param UnpackOptions|int|null $options
     *
     * @return mixed
     *
     * @throws \MessagePack\Exception\InvalidOptionException
     * @throws \MessagePack\Exception\UnpackingFailedException
     */
    public static function unpack($data, $options = null)
    {
        return (new BufferUnpacker($data, $options))->unpack();
    }
}
