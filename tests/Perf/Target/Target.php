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

interface Target
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @param Test $test
     *
     * @throws \Exception
     */
    public function sanitize(Test $test);

    /**
     * @param Test $test
     */
    public function perform(Test $test);

    /**
     * @param Test $test
     */
    public function calibrate(Test $test);
}
