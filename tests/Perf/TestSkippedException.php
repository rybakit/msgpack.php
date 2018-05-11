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

class TestSkippedException extends \RuntimeException
{
    private $test;

    public function __construct(Test $test, int $code = null, \Exception $previous = null)
    {
        $message = sprintf('"%s" test is skipped.', $test->getName());

        parent::__construct($message, $code, $previous);

        $this->test = $test;
    }

    public function getTest() : Test
    {
        return $this->test;
    }
}
