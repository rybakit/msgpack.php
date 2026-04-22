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

final class DateTimeExtension implements Extension
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
        if (!$value instanceof \DateTimeInterface) {
            return null;
        }

        return $packer->packExt($this->type, $value->format('YmdHisue'));
    }

    /**
     * @return \DateTimeImmutable|false
     */
    #[\Override]
    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        return \DateTimeImmutable::createFromFormat('YmdHisue', $unpacker->read($extLength));
    }
}
