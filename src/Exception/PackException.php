<?php

namespace MessagePack\Exception;

class PackException extends \RuntimeException
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
