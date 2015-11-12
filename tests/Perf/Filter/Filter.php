<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Perf\Filter;

interface Filter
{
    /**
     * @param string $item
     *
     * @return bool
     */
    public function isAccepted($item);
}
