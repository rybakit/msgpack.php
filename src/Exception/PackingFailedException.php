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

    public function __construct($value, $message = null, $code = null, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
