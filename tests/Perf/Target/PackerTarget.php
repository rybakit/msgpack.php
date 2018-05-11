<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Perf\Target;

use MessagePack\Packer;
use MessagePack\Tests\Perf\Test;

class PackerTarget implements Target
{
    private $name;
    private $packer;

    public function __construct(string $name = null, Packer $packer = null)
    {
        $this->packer = $packer ?: new Packer();
        $this->name = $name ?: get_class($this->packer);
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function sanitize(Test $test) : void
    {
    }

    public function perform(Test $test) : void
    {
        $this->packer->pack($test->getRaw());
    }

    public function calibrate(Test $test) : void
    {
        $test->getRaw();
    }
}
