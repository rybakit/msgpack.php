<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\MessagePack;

use MessagePack\BufferUnpacker;
use MessagePack\Extension;
use MessagePack\Packer;

final class TextExtension implements Extension
{
    public function __construct(
        private readonly int $type,
        private readonly int $minLength = 100,
    ) {
    }

    #[\Override]
    public function getType() : int
    {
        return $this->type;
    }

    /**
     * @param mixed $value
     */
    #[\Override]
    public function pack(Packer $packer, $value) : ?string
    {
        if (!$value instanceof Text) {
            return null;
        }

        $length = \strlen($value->str);
        if ($length < $this->minLength) {
            return $packer->packStr($value->str);
        }

        $context = \deflate_init(\ZLIB_ENCODING_GZIP);
        if (false === $context) {
            return $packer->packStr($value->str);
        }

        $compressed = \deflate_add($context, $value->str, \ZLIB_FINISH);
        if (false === $compressed) {
            return $packer->packStr($value->str);
        }

        return isset($compressed[$length - 1])
            ? $packer->packStr($value->str)
            : $packer->packExt($this->type, $compressed);
    }

    /**
     * @return string|false
     */
    #[\Override]
    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        $compressed = $unpacker->read($extLength);
        $context = \inflate_init(\ZLIB_ENCODING_GZIP);

        return false === $context
            ? false
            : \inflate_add($context, $compressed, \ZLIB_FINISH);
    }
}
