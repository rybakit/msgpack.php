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

use MessagePack\Tests\Perf\Test;

class NameFilter implements Filter
{
    private $whitelist = [];
    private $blacklist = [];

    public function __construct(array $names)
    {
        foreach ($names as $name) {
            $name = trim($name);

            if ('-' !== $name[0]) {
                $this->whitelist[] = $name;
                continue;
            }

            $this->blacklist[] = substr($name, 1);
        }
    }

    public function reset()
    {
        $this->whitelist = [];
        $this->blacklist = [];
    }

    public function isAccepted(Test $test)
    {
        if (in_array($test->getName(), $this->blacklist, true)) {
            return false;
        }

        if ($this->whitelist && !in_array($test->getName(), $this->whitelist, true)) {
            return false;
        }

        return true;
    }
}
