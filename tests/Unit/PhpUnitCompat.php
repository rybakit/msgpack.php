<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit;

/**
 * Compatibility layer for legacy PHPUnit versions.
 */
trait PhpUnitCompat
{
    /**
     * TestCase::expectExceptionMessageRegExp() is deprecated since PHPUnit 8.4.
     */
    public function expectExceptionMessageMatches(string $regularExpression) : void
    {
        is_callable(parent::class.'::expectExceptionMessageMatches')
            ? parent::expectExceptionMessageMatches(...func_get_args())
            : parent::expectExceptionMessageRegExp(...func_get_args());
    }
}
