<?php

require __DIR__.'/../vendor/autoload.php';

if (!class_exists('\PHPUnit_Framework_TestCase') && class_exists('\PHPUnit\Framework\TestCase')) {
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
}

if (!class_exists('\PHPUnit_Framework_Error_Warning') && class_exists('\PHPUnit\Framework\Error\Warning')) {
    class_alias('\PHPUnit\Framework\Error\Warning', '\PHPUnit_Framework_Error_Warning');
}
