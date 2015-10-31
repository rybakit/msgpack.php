<?php

namespace MessagePack\Exception;

class InsufficientDataException extends UnpackException
{
    public function __construct($expectedLenth, $actualLenth, $code = null, \Exception $previous = null)
    {
        $message = sprintf('Not enough data (%d of %d).', $actualLenth, $expectedLenth);

        parent::__construct($message, $code, $previous);
    }
}
