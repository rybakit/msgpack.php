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

    /**
     * @dataProvider provideParseValidDateData
     */
    public function testParseValidDateSucceeds(string $datetime, int $seconds, int $nanoseconds) : void
    {
        $timestamp = Timestamp::parse($datetime);

        self::assertSame($seconds, $timestamp->getSeconds());
        self::assertSame($nanoseconds, $timestamp->getNanoseconds());
    }

    public function provideParseValidDateData() : iterable
    {
        return [
            ['1970-01-01 00:00:00 UTC', 0, 0],
            ['2106-02-07 06:28:15 UTC', 0xffffffff, 0],
            ['1970-01-01 00:00:00.000000001 UTC', 0, 1],
            ['2022-01-29 07.08.09Z', 1643440089, 0],
            ['2022-01-29 07:08:09Z', 1643440089, 0],
            ['2022-01-29 07:08:09.1Z', 1643440089, 100000000],
            ['2022-01-29 07:08:09.12Z', 1643440089, 120000000],
            ['2022-01-29 07:08:09.123Z', 1643440089, 123000000],
            ['2022-01-29 07:08:09.1234Z', 1643440089, 123400000],
            ['2022-01-29 07:08:09.12345Z', 1643440089, 123450000],
            ['2022-01-29 07:08:09.123456Z', 1643440089, 123456000],
            ['2022-01-29 07:08:09.1234567Z', 1643440089, 123456700],
            ['2022-01-29 07:08:09.12345678Z', 1643440089, 123456780],
            ['2022-01-29 07:08:09.123456789Z', 1643440089, 123456789],
            ['2022-01-29 07:08:09.999999999Z', 1643440089, 999999999],
            ['2022-01-29 07.08.09.999999999Z', 1643440089, 999999999],
            ['2022-01-29 7:08:09:123456789am UTC', 1643440089, 123456789],
            ['2022-01-29 08.08.09.123456789 +01:00', 1643440089, 123456789],
            ['2022-01-29 07:08:09.123456789Z +1 week +1 hour', 1644048489, 123456789],
        ];
    }

    /**
     * @dataProvider provideParseInvalidDateData
     */
    public function testParseInvalidDateFails(string $datetime) : void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Failed to parse date: $datetime");

        Timestamp::parse($datetime);
    }

    public function provideParseInvalidDateData() : iterable
    {
        return [
            ['foobar'],
            ['12345'],
        ];
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
