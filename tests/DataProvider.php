<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Most of the test data in this file are borrowed from
 * https://github.com/vsergeev/u-msgpack-python/blob/master/test_umsgpack.py
 */

namespace MessagePack\Tests;

use MessagePack\Ext;

class DataProvider
{
    public static function provideData()
    {
        return [
            ['nil', null, "\xc0"],
            ['false', false, "\xc2"],
            ['true', true, "\xc3"],

            ['7-bit uint #1', 0x00, "\x00"],
            ['7-bit uint #2', 0x10, "\x10"],
            ['7-bit uint #3', 0x7f, "\x7f"],

            ['5-bit sint #1', -1, "\xff"],
            ['5-bit sint #2', -16, "\xf0"],
            ['5-bit sint #3', -32, "\xe0"],

            ['8-bit uint #1', 0x80, "\xcc\x80"],
            ['8-bit uint #2', 0xf0, "\xcc\xf0"],
            ['8-bit uint #3', 0xff, "\xcc\xff"],

            ['16-bit uint #1', 0x100, "\xcd\x01\x00"],
            ['16-bit uint #2', 0x2000, "\xcd\x20\x00"],
            ['16-bit uint #3', 0xffff, "\xcd\xff\xff"],

            ['32-bit uint #1', 0x10000, "\xce\x00\x01\x00\x00"],
            ['32-bit uint #2', 0x200000, "\xce\x00\x20\x00\x00"],
            ['32-bit uint #3', 0xffffffff, "\xce\xff\xff\xff\xff"],

            ['64-bit uint #1', 0x100000000, "\xcf"."\x00\x00\x00\x01"."\x00\x00\x00\x00"],
            ['64-bit uint #2', 0x7fffffffffffffff, "\xcf"."\x7f\xff\xff\xff"."\xff\xff\xff\xff"],

            ['8-bit int #1', -33, "\xd0\xdf"],
            ['8-bit int #2', -100, "\xd0\x9c"],
            ['8-bit int #3', -128, "\xd0\x80"],

            ['16-bit int #1', -129, "\xd1\xff\x7f"],
            ['16-bit int #2', -2000, "\xd1\xf8\x30"],
            ['16-bit int #3', -32768, "\xd1\x80\x00"],

            ['32-bit int #1', -32769, "\xd2\xff\xff\x7f\xff"],
            ['32-bit int #2', -1000000000, "\xd2\xc4\x65\x36\x00"],
            ['32-bit int #3', -2147483648, "\xd2\x80\x00\x00\x00"],

            ['64-bit int #1', -2147483649, "\xd3"."\xff\xff\xff\xff"."\x7f\xff\xff\xff"],
            ['64-bit int #2', -1000000000000000002, "\xd3"."\xf2\x1f\x49\x4c"."\x58\x9b\xff\xfe"],
            // https://bugs.php.net/bug.php?id=53934
            ['64-bit int #3', -PHP_INT_MAX - 1, "\xd3"."\x80\x00\x00\x00"."\x00\x00\x00\x00"],

            ['64-bit float #1', 0.0, "\xcb"."\x00\x00\x00\x00"."\x00\x00\x00\x00"],
            ['64-bit float #2', 2.5, "\xcb"."\x40\x04\x00\x00"."\x00\x00\x00\x00"],
            ['64-bit float #3', pow(10, 35), "\xcb"."\x47\x33\x42\x61"."\x72\xc7\x4d\x82"],

            ['fix string #1', '', "\xa0"],
            ['fix string #2', 'a', "\xa1\x61"],
            ['fix string #3', 'abc', "\xa3\x61\x62\x63"],
            ['fix string #4', str_repeat('a', 31), "\xbf".str_repeat("\x61", 31)],

            ['8-bit string #1', str_repeat('b', 32), "\xd9\x20".str_repeat('b', 32)],
            ['8-bit string #2', str_repeat('c', 100), "\xd9\x64".str_repeat('c', 100)],
            ['8-bit string #3', str_repeat('d',  255), "\xd9\xff".str_repeat('d',  255)],
            ['16-bit string #1', str_repeat('b', 256), "\xda\x01\x00".str_repeat('b', 256)],
            ['16-bit string #2', str_repeat('c', 65535), "\xda\xff\xff".str_repeat('c', 65535)],
            ['32-bit string', str_repeat('b', 65536), "\xdb\x00\x01\x00\x00".str_repeat('b', 65536)],

            ['wide char string #1', 'Allagbé', "\xa8Allagb\xc3\xa9"],
            ['wide char string #2', 'По оживлённым берегам', "\xd9\x28\xd0\x9f\xd0\xbe\x20\xd0\xbe\xd0\xb6\xd0\xb8\xd0\xb2\xd0\xbb\xd1\x91\xd0\xbd\xd0\xbd\xd1\x8b\xd0\xbc\x20\xd0\xb1\xd0\xb5\xd1\x80\xd0\xb5\xd0\xb3\xd0\xb0\xd0\xbc"],

            ['8-bit binary #1', "\x80", "\xc4\x01"."\x80"],
            ['8-bit binary #2', str_repeat("\x80", 32), "\xc4\x20".str_repeat("\x80", 32)],
            ['8-bit binary #3', str_repeat("\x80", 255), "\xc4\xff".str_repeat("\x80", 255)],
            ['16-bit binary', str_repeat("\x80", 256), "\xc5\x01\x00".str_repeat("\x80", 256)],
            ['32-bit binary', str_repeat("\x80", 65536), "\xc6\x00\x01\x00\x00".str_repeat("\x80", 65536)],

            ['fixext 1', new Ext(5, "\x80"), "\xd4\x05"."\x80"],
            ['fixext 2', new Ext(5, str_repeat("\x80", 2)), "\xd5\x05".str_repeat("\x80", 2)],
            ['fixext 4', new Ext(5, str_repeat("\x80", 4)), "\xd6\x05".str_repeat("\x80", 4)],
            ['fixext 8', new Ext(5, str_repeat("\x80", 8)), "\xd7\x05".str_repeat("\x80", 8)],
            ['fixext 16', new Ext(5, str_repeat("\x80", 16)), "\xd8\x05".str_repeat("\x80", 16)],
            ['8-bit ext', new Ext(5, str_repeat("\x80", 255)), "\xc7\xff\x05".str_repeat("\x80", 255)],
            ['16-bit ext', new Ext(5, str_repeat("\x80", 256)), "\xc8\x01\x00\x05".str_repeat("\x80", 256)],
            ['32-bit ext', new Ext(5, str_repeat("\x80", 65536)), "\xc9\x00\x01\x00\x00\x05".str_repeat("\x80", 65536)],

            ['fix array #1', [], "\x90"],
            ['fix array #2', [5, 'abc', true], "\x93\x05\xa3\x61\x62\x63\xc3"],
            ['16-bit array #1', array_fill(0, 16, 0x05), "\xdc\x00\x10".str_repeat("\x05", 16)],
            ['16-bit array #2', array_fill(0, 65535, 0x05), "\xdc\xff\xff".str_repeat("\x05", 65535)],
            ['32-bit array', array_fill(0, 65536, 0x05), "\xdd\x00\x01\x00\x00".str_repeat("\x05", 65536)],
            ['complex array', [true, 0x01, new Ext(3, 'foo'), 0xff, [1 => false, 2 => 'abc'], "\x80", [1, 2, 3], 'abc'], "\x98\xc3\x01\xc7\x03\x03\x66\x6f\x6f\xcc\xff\x82\x01\xc2\x02\xa3\x61\x62\x63\xc4\x01\x80\x93\x01\x02\x03\xa3\x61\x62\x63"],

            ['fix map #1', [1 => true, 2 => 'abc', 3 => "\x80", 4 => null], "\x84\x01\xc3\x02\xa3\x61\x62\x63\x03\xc4\x01\x80\x04\xc0"],
            ['fix map #2', ['abc' => 5], "\x81\xa3\x61\x62\x63\x05"],
            ['fix map #3', ["\x80" => 0xffff], "\x81\xc4\x01\x80\xcd\xff\xff"],
            ['16-bit map #1', array_fill(1, 16, 0x05), "\xde\x00\x10".array_reduce(range(1, 16), function ($r, $i) { return $r .= pack('C', $i)."\x05"; })],
            ['16-bit map #2', array_fill(1, 65535, 0x05), "\xde\xff\xff".array_reduce(range(1, 127), function ($r, $i) { return $r .= pack('C', $i)."\x05"; }).array_reduce(range(128, 255), function ($r, $i) { return $r .= "\xcc".pack('C', $i)."\x05"; }).array_reduce(range(256, 65535), function ($r, $i) { return $r .= "\xcd".pack('n', $i)."\x05"; })],
            ['32-bit map', array_fill(1, 65536, 0x05), "\xdf\x00\x01\x00\x00".array_reduce(range(1, 127), function ($r, $i) { return $r .= pack('C', $i)."\x05"; }).array_reduce(range(128, 255), function ($r, $i) { return $r .= "\xcc".pack('C', $i)."\x05"; }).array_reduce(range(256, 65535), function ($r, $i) { return $r .= "\xcd".pack('n', $i)."\x05"; })."\xce".pack('N', 65536)."\x05"],
            ['complex map', [1 => [[1 => 2, 3 => 4], [1 => null]], 2 => 1, 3 => [false, 'def'], 4 => [0x100000000 => 'a', 0xffffffff => 'b']], "\x84\x01\x92\x82\x01\x02\x03\x04\x81\x01\xc0\x02\x01\x03\x92\xc2\xa3\x64\x65\x66\x04\x82\xcf\x00\x00\x00\x01\x00\x00\x00\x00\xa1\x61\xce\xff\xff\xff\xff\xa1\x62"],
        ];
    }

    public static function provideUnpackData()
    {
        return array_merge(self::provideData(), [
            ['32-bit float #1', 0.0, "\xca"."\x00\x00\x00\x00"],
            ['32-bit float #2', 2.5, "\xca"."\x40\x20\x00\x00"],

            ['fix map #1', [], "\x80"],
            ['fix map #2', [0 => null], "\x81\xc2\xc0"],
            ['fix map #3', [1 => null], "\x81\xc3\xc0"],

            ['64-bit uint #3', 0, "\xcf"."\x00\x00\x00\x00"."\x00\x00\x00\x00"],
        ]);
    }
}
