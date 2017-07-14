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
     * @param mixed $value
     * @param int|null $typeDetectionMode
     *
     * @return string
     *
     * @throws \InvalidArgumentException|\MessagePack\Exception\PackingFailedException
     */
    public static function pack($value, $typeDetectionMode = null)
    {
        return (new Packer($typeDetectionMode))->pack($value);
    }

    /**
     * @param string $data
     * @param int|null $intOverflowMode
     *
     * @return mixed
     *
     * @throws \MessagePack\Exception\UnpackingFailedException
     */
    public static function unpack($data, $intOverflowMode = null)
    {
        return (new BufferUnpacker($intOverflowMode))->reset($data)->unpack();
    }

    private function __construct()
    {
    }
}
