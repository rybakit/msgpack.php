<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Exception;

class InvalidOptionException extends \InvalidArgumentException
{
    public static function fromValidOptions(string $invalidOption, array $validOptions) : self
    {
        $use = \count($validOptions) > 2
            ? \sprintf('one of %2$s or %1$s', \array_pop($validOptions), \implode(', ', $validOptions))
            : \implode(' or ', $validOptions);

        return new self(\sprintf('Invalid option %s, use %s.', $invalidOption, $use));
    }
}
