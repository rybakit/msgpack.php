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

class RegexpFilter implements Filter
{
    private $regexp;

    public function __construct(string $regexp)
    {
        $this->regexp = $regexp;
    }

    public function isAccepted(Test $test) : bool
    {
        return 1 === \preg_match($this->regexp, $test->getName());
    }
}
