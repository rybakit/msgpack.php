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

class ListFilter implements Filter
{
    private $whitelist = [];
    private $blacklist = [];

    public function __construct(array $items)
    {
        foreach ($items as $item) {
            $item = trim($item);

            if ('-' !== $item[0]) {
                $this->whitelist[] = $item;
                continue;
            }

            $this->blacklist[] = substr($item, 1);
        }
    }

    public function reset()
    {
        $this->whitelist = [];
        $this->blacklist = [];
    }

    public function isAccepted($item)
    {
        if (in_array($item, $this->blacklist, true)) {
            return false;
        }

        if ($this->whitelist && !in_array($item, $this->whitelist, true)) {
            return false;
        }

        return true;
    }
}
