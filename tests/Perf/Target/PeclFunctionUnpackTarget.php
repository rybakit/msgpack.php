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

class PeclFunctionUnpackTarget implements Target
{
    public function getName() : string
    {
        return 'msgpack_unpack';
    }

    public function sanitize(Test $test) : void
    {
        if ($test->getRaw() !== \msgpack_unpack($test->getPacked())) {
            throw new \UnexpectedValueException('$raw !== msgpack_unpack($packed)');
        }
    }

    public function perform(Test $test) : void
    {
        \msgpack_unpack($test->getPacked());
    }

    public function calibrate(Test $test) : void
    {
        $test->getPacked();
    }
}
