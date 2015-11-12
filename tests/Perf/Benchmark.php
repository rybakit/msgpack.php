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

interface Benchmark
{
    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return int
     */
    public function getSize();

    /**
     * @param mixed  $raw
     * @param string $packed
     *
     * @return float
     */
    public function measure($raw, $packed);
}
