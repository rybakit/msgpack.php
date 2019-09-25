<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\TypeTransformer;

use MessagePack\Packer;

abstract class Extension implements CanPack, CanUnpackExt
{
    private $type;

    public function __construct(int $type)
    {
        $this->type = $type;
    }

    public function getType() : int
    {
        return $this->type;
    }

    public function pack(Packer $packer, $value) : ?string
    {
        if (null === $data = $this->packExt($packer, $value)) {
            return null;
        }

        return $packer->packExt($this->type, $data);
    }

    abstract protected function packExt(Packer $packer, $value) : ?string;
}
