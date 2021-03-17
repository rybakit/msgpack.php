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
use MessagePack\Packer;
use MessagePack\TypeTransformer\Extension;

class TextExtension implements Extension
{
    private $type;
    private $minLength;

    public function __construct(int $type, int $minLength = 30)
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

        if (strlen($value->str) < $this->minLength) {
            return $packer->packStr($value->str);
        }

        $context = \deflate_init(\ZLIB_ENCODING_GZIP);
        $compressed = \deflate_add($context, $value->str, \ZLIB_FINISH);

        return $packer->packExt($this->type, $packer->packBin($compressed));
    }

    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        $compressed = $unpacker->unpackBin();

        $context = \inflate_init(ZLIB_ENCODING_GZIP);

        return \inflate_add($context, $compressed, \ZLIB_FINISH);
    }
}
