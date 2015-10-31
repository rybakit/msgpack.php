<?php

namespace MessagePack\Tests\Exception;

use MessagePack\Exception\PackException;

class PackExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $value = (object) ['foo' => 'bar'];
        $errorMessage = 'Error message';
        $errorCode = 42;
        $prevException = new \Exception();

        $exception = new PackException($value, $errorMessage, $errorCode, $prevException);

        $this->assertSame($value, $exception->getValue());
        $this->assertSame($errorMessage, $exception->getMessage());
        $this->assertSame($errorCode, $exception->getCode());
        $this->assertSame($prevException, $exception->getPrevious());
    }
}
