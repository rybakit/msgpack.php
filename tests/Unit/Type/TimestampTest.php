<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\Type;

use MessagePack\Type\Timestamp;
use PHPUnit\Framework\TestCase;

final class TimestampTest extends TestCase
{
    public function testPropertiesAreSet() : void
    {
        $seconds = 42;
        $nanoseconds = 999999999;
        $timestamp = new Timestamp($seconds, $nanoseconds);

        self::assertSame($seconds, $timestamp->getSeconds());
        self::assertSame($nanoseconds, $timestamp->getNanoseconds());
    }

    public function testNow() : void
    {
        $now = new \DateTime('UTC');
        $timestamp = Timestamp::now();

        self::assertSame($now->getTimestamp(), $timestamp->getSeconds());
        self::assertTrue((int) $now->format('u') * 1000 <= $timestamp->getNanoseconds());
    }

    public function testFromDateTime() : void
    {
        $date = new \DateTime('@42.123456');
        $timestamp = Timestamp::fromDateTime($date);

        self::assertSame(42, $timestamp->getSeconds());
        self::assertSame(123456000, $timestamp->getNanoseconds());
    }
}
