<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit;

use MessagePack\BufferUnpacker;
use MessagePack\CanBePacked;
use MessagePack\MessagePack;
use MessagePack\Packer;
use MessagePack\Type\Bin;
use MessagePack\Type\Ext;
use MessagePack\Type\Map;
use MessagePack\Type\Timestamp;
use PHPUnit\Framework\TestCase;

final class MessagePackVectorsTest extends TestCase
{
    private const VECTOR_FILE = __DIR__.'/../fixtures/msgpack-test-suite/msgpack-test-suite.json';

    /**
     * @dataProvider provideUnpackVectorData
     */
    public function testUnpackVector(string $type, mixed $value, string $encoding) : void
    {
        $bytes = self::decodeEncoding($encoding);
        $actual = MessagePack::unpack($bytes);
        $expected = self::expectedDecodedValue($type, $value, $encoding);

        self::assertSame(self::normalize($expected), self::normalize($actual));

        if ('binary' === $type) {
            self::assertSame($actual, (new BufferUnpacker($bytes))->unpackBin());
        } elseif ('string' === $type) {
            self::assertSame($actual, (new BufferUnpacker($bytes))->unpackStr());
        }
    }

    /**
     * @dataProvider providePackVectorData
     */
    public function testPackVector(string $type, mixed $value, array $encodings) : void
    {
        $actual = MessagePack::pack(self::valueForPacking($type, $value));
        $expected = array_map([self::class, 'decodeEncoding'], $encodings);

        self::assertContains($actual, $expected);
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function provideUnpackVectorData() : array
    {
        $data = [];

        foreach (self::vectorCases() as $file => $cases) {
            $tag = substr($file, 0, -5);

            foreach ($cases as $caseIndex => $case) {
                [$type, $value] = self::caseValue($case);

                foreach ($case->msgpack as $encodingIndex => $encoding) {
                    $data["$tag:$caseIndex:$encodingIndex"] = [$type, $value, $encoding];
                }
            }
        }

        return $data;
    }

    /**
     * @return array<string, array{string, mixed, string[]}>
     */
    public static function providePackVectorData() : array
    {
        $data = [];

        foreach (self::vectorCases() as $file => $cases) {
            $tag = substr($file, 0, -5);

            foreach ($cases as $caseIndex => $case) {
                [$type, $value] = self::caseValue($case);
                $data["$tag:$caseIndex:encode"] = [$type, $value, $case->msgpack];
            }
        }

        return $data;
    }

    /**
     * @return array<string, array<int, \stdClass>>
     */
    private static function vectorCases() : array
    {
        static $vectors;

        if (null === $vectors) {
            if (!is_file(self::VECTOR_FILE)) {
                throw new \RuntimeException('Vendored MessagePack vectors are missing.');
            }

            $json = file_get_contents(self::VECTOR_FILE);
            if (false === $json) {
                throw new \RuntimeException('Unable to read MessagePack vectors.');
            }

            $vectors = get_object_vars(json_decode($json, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING));
        }

        return $vectors;
    }

    /**
     * @return array{string, mixed}
     */
    private static function caseValue(\stdClass $case) : array
    {
        $values = get_object_vars($case);
        unset($values['msgpack']);

        if (isset($values['bignum'])) {
            return ['bignum', $values['bignum']];
        }

        $type = array_key_first($values);

        return [$type, $values[$type]];
    }

    private static function expectedDecodedValue(string $type, mixed $value, string $encoding) : mixed
    {
        return match ($type) {
            'binary' => self::decodeEncoding($value),
            'bignum' => self::decodedBignumForEncoding((string) $value, $encoding),
            'ext' => new Ext((int) $value[0], self::decodeEncoding($value[1])),
            'map', 'array' => self::toPhpArray($value),
            'number' => self::decodedNumber($value, $encoding),
            'timestamp' => new Timestamp((int) $value[0], (int) $value[1]),
            default => $value,
        };
    }

    private static function decodedNumber(int|float $value, string $encoding) : int|float
    {
        $bytes = self::decodeEncoding($encoding);

        if (0xca === ord($bytes[0])) {
            return unpack('G', pack('G', (float) $value))[1];
        }

        if (0xcb === ord($bytes[0])) {
            return (float) $value;
        }

        return (int) $value;
    }

    private static function decodedBignum(string $value) : int|string
    {
        if (gmp_cmp($value, (string) PHP_INT_MIN) >= 0 && gmp_cmp($value, (string) PHP_INT_MAX) <= 0) {
            return (int) $value;
        }

        return $value;
    }

    private static function decodedBignumForEncoding(string $value, string $encoding) : int|float|string
    {
        $bytes = self::decodeEncoding($encoding);

        if (0xca === ord($bytes[0]) || 0xcb === ord($bytes[0])) {
            return self::decodedNumber((float) $value, $encoding);
        }

        return self::decodedBignum($value);
    }

    private static function valueForPacking(string $type, mixed $value) : mixed
    {
        return match ($type) {
            'binary' => new Bin(self::decodeEncoding($value)),
            'bignum' => self::packableBignum((string) $value),
            'ext' => new Ext((int) $value[0], self::decodeEncoding($value[1])),
            'map', 'array' => self::toPackableContainers($value),
            'timestamp' => new Timestamp((int) $value[0], (int) $value[1]),
            default => $value,
        };
    }

    private static function packableBignum(string $value) : int|VectorUint64
    {
        if (gmp_cmp($value, (string) PHP_INT_MIN) >= 0 && gmp_cmp($value, (string) PHP_INT_MAX) <= 0) {
            return (int) $value;
        }

        return new VectorUint64($value);
    }

    private static function toPackableContainers(mixed $value) : mixed
    {
        if ($value instanceof \stdClass) {
            $map = [];
            foreach (get_object_vars($value) as $key => $item) {
                $map[$key] = self::toPackableContainers($item);
            }

            return new Map($map);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::toPackableContainers($item);
            }
        }

        return $value;
    }

    private static function toPhpArray(mixed $value) : mixed
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::toPhpArray($item);
            }
        }

        return $value;
    }

    private static function normalize(mixed $value) : mixed
    {
        if ($value instanceof Timestamp) {
            return ['timestamp', $value->getSeconds(), $value->getNanoseconds()];
        }

        if ($value instanceof Ext) {
            return ['ext', $value->type, $value->data];
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }

    private static function decodeEncoding(string $encoding) : string
    {
        $bytes = hex2bin(str_replace('-', '', $encoding));
        if (false === $bytes) {
            throw new \RuntimeException("Invalid MessagePack encoding: $encoding");
        }

        return $bytes;
    }
}

final class VectorUint64 implements CanBePacked
{
    public function __construct(private string $value)
    {
    }

    public function pack(Packer $packer) : string
    {
        return "\xcf".gmp_export($this->value);
    }
}
