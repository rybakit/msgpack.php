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

class PeclPacking implements Benchmark
{
    private $size;

    public function __construct($size)
    {
        $this->size = $size;
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle()
    {
        return 'msgpack_pack';
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function measure($raw, $packed)
    {
        assert($packed === msgpack_pack($raw));

        $totalTime = 0;

        for ($i = $this->size; $i; $i--) {
            $time = microtime(true);
            msgpack_pack($raw);
            $totalTime += microtime(true) - $time;
        }

        return $totalTime;
    }
}
