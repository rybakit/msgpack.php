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

class TextExtension implements Extension
{
    private $type;
    private $minLength;

    public function __construct(int $type, int $minLength = 100)
    {
        $this->type = $type;
        $this->minLength = $minLength;
    }

    public function getType() : int
    {
        return $this->type;
    }

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
        $compressed = \deflate_add($context, $value->str, \ZLIB_FINISH);

        return isset($compressed[$length - 1])
            ? $packer->packStr($value->str)
            : $packer->packExt($this->type, $compressed);
    }

    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        $compressed = $unpacker->read($extLength);
        $context = \inflate_init(\ZLIB_ENCODING_GZIP);

        return \inflate_add($context, $compressed, \ZLIB_FINISH);
    }
}
