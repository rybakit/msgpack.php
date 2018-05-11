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

class RegexFilter implements Filter
{
    private $regex;

    public function __construct(string $regex)
    {
        $this->regex = $regex;
    }

    public function isAccepted(Test $test) : bool
    {
        return 1 === preg_match($this->regex, $test->getName());
    }
}
