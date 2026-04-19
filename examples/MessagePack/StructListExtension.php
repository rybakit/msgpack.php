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

final class StructListExtension implements Extension
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
        if (!$value instanceof StructList) {
            return null;
        }

        $size = \count($value->list);
        if ($size < 2) {
            return $packer->packArray($value->list);
        }

        $first = \reset($value->list);
        if (false === $first) {
            return $packer->packArray($value->list);
        }

        $keys = \array_keys($first);

        $values = '';
        foreach ($value->list as $item) {
            foreach ($keys as $key) {
                $values .= $packer->pack($item[$key]);
            }
        }

        return $packer->packExt($this->type,
            $packer->packArray($keys).
            $packer->packArrayHeader($size).
            $values
        );
    }

    /**
     * @return array<int, array<array-key, mixed>>
     */
    #[\Override]
    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        $keys = $unpacker->unpackArray();
        $size = $unpacker->unpackArrayHeader();

        $list = [];
        for ($i = 0; $i < $size; ++$i) {
            foreach ($keys as $key) {
                $list[$i][$key] = $unpacker->unpack();
            }
        }

        return $list;
    }
}
