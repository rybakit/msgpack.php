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

class IntegerOverflowException extends UnpackingFailedException
{
    private $value;

    public function __construct(int $value)
    {
        parent::__construct(\sprintf('The value is too big: %u.', $value));

        $this->value = $value;
    }

    public function getValue() : int
    {
        return $this->value;
    }
}
