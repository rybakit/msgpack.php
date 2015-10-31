<?php

namespace MessagePack\Exception;

class InsufficientDataException extends UnpackException
{
    public function __construct($expectedLength, $actualLength, $code = null, \Exception $previous = null)
    {
        $message = sprintf('Not enough data to unpack: need %d, have %d.', $expectedLength, $actualLength);

        parent::__construct($message, $code, $previous);
    }
}
