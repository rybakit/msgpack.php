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
    private $packer;

    public function __construct(Packer $packer = null)
    {
        $this->packer = $packer ?: new Packer();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return get_class($this->packer);
    }

    /**
     * {@inheritdoc}
     */
    public function sanitize(Test $test)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function perform(Test $test)
    {
        $this->packer->pack($test->getRaw());
    }

    /**
     * {@inheritdoc}
     */
    public function calibrate(Test $test)
    {
        $test->getRaw();
    }
}
