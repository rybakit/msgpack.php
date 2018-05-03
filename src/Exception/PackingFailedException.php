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

class PackingFailedException extends \RuntimeException
{
    private $value;

    public function __construct($value, string $message = '', \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public static function unsupportedType($value) : self
    {
        $message = \sprintf('Unsupported type: %s.',
            \is_object($value) ? \get_class($value) : \gettype($value)
        );

        return new self($value, $message);
    }
}
