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

use MessagePack\Tests\Perf\Test;

class PeclFunctionPackTarget implements Target
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'msgpack_pack';
    }

    /**
     * {@inheritdoc}
     */
    public function sanitize(Test $test)
    {
        if ($test->getPacked() !== msgpack_pack($test->getRaw())) {
            throw new \UnexpectedValueException('$packed !== msgpack_pack($raw)');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function perform(Test $test)
    {
        msgpack_pack($test->getRaw());
    }

    /**
     * {@inheritdoc}
     */
    public function calibrate(Test $test)
    {
        $test->getRaw();
    }
}
