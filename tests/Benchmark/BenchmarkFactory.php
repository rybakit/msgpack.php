<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Benchmark;

class BenchmarkFactory
{
    const PECL_P = 'pecl_p';
    const PECL_U = 'pecl_u';
    const PURE_P = 'pure_p';
    const PURE_U = 'pure_u';

    public static function create($target, $size)
    {
        static $map;

        $map = [
            self::PECL_P => function ($size) { return new PeclPacking($size); },
            self::PECL_U => function ($size) { return new PeclUnpacking($size); },
            self::PURE_P => function ($size) { return new PurePacking($size); },
            self::PURE_U => function ($size) { return new PureUnpacking($size); },
        ];

        if (!isset($map[$target])) {
            throw new \InvalidArgumentException(sprintf('Invalid benchmark target "%s".', $target));
        }

        return $map[$target]($size);
    }
}
