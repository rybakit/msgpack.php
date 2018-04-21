<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Perf;

final class Test
{
    private $name;
    private $raw;
    private $packed;

    public function __construct(string $name, $raw, string $packed)
    {
        $this->name = $name;
        $this->raw = $raw;
        $this->packed = $packed;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getRaw()
    {
        return $this->raw;
    }

    public function getPacked() : string
    {
        return $this->packed;
    }

    public function __toString() : string
    {
        return $this->getName();
    }
}
