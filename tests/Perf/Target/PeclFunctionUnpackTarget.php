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
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'msgpack_unpack';
    }

    /**
     * {@inheritdoc}
     */
    public function ensureSanity(Test $test)
    {
        if ($test->getRaw() !== msgpack_unpack($test->getPacked())) {
            throw new \UnexpectedValueException('$raw !== msgpack_unpack($packed)');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function measure(Test $test)
    {
        $packed = $test->getPacked();

        $time = microtime(true);
        msgpack_unpack($packed);

        return microtime(true) - $time;
    }
}
