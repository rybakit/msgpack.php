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

class TargetFactory
{
    const PECL_P = 'pecl_p';
    const PECL_U = 'pecl_u';
    const PURE_P = 'pure_p';
    const PURE_U = 'pure_u';

    public static function create($name)
    {
        static $map;

        $map = [
            self::PECL_P => function () { return new PeclFunctionPackTarget(); },
            self::PECL_U => function () { return new PeclFunctionUnpackTarget(); },
            self::PURE_P => function () { return new PackerTarget(); },
            self::PURE_U => function () { return new BufferUnpackerTarget(); },
        ];

        if (!isset($map[$name])) {
            throw new \InvalidArgumentException(sprintf('Invalid target "%s".', $name));
        }

        return $map[$name]();
    }
}
