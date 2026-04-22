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

final class TraversableExtension implements Extension
{
    public function __construct(
        private readonly int $type,
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
        if (!$value instanceof \Traversable) {
            return null;
        }

        $data = '';
        $size = 0;
        foreach ($value as $item) {
            $data .= $packer->pack($item);
            ++$size;
        }

        return $packer->packExt($this->type,
            $packer->packArrayHeader($size).$data
        );
    }

    /**
     * @return \Generator
     */
    #[\Override]
    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        $size = $unpacker->unpackArrayHeader();

        while ($size--) {
            yield $unpacker->unpack();
        }
    }
}
