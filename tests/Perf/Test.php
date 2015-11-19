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

    public function __construct($name, $raw, $packed)
    {
        $this->name = $name;
        $this->raw = $raw;
        $this->packed = $packed;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getRaw()
    {
        return $this->raw;
    }

    /**
     * @return string
     */
    public function getPacked()
    {
        return $this->packed;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
}
