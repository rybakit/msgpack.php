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
    public function getName() : string
    {
        return 'msgpack_pack';
    }

    public function sanitize(Test $test) : void
    {
        if ($test->getPacked() !== \msgpack_pack($test->getRaw())) {
            throw new \UnexpectedValueException('$packed !== msgpack_pack($raw)');
        }
    }

    public function perform(Test $test) : void
    {
        \msgpack_pack($test->getRaw());
    }

    public function calibrate(Test $test) : void
    {
        $test->getRaw();
    }
}
