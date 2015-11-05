<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack;

use MessagePack\Exception\InsufficientDataException;
use MessagePack\Exception\IntegerOverflowException;
use MessagePack\Exception\UnpackException;

class BufferUnpacker
{
    const BIGINT_MODE = 'bigint_mode';

    const BIGINT_MODE_STR = 1;
    const BIGINT_MODE_GMP = 2;

    /**
     * @var string
     */
    private $buffer;

    /**
     * @var int
     */
    private $offset = 0;

    /**
     * @var array
     */
    private $options = [self::BIGINT_MODE => 0];

    private static $map;

    /**
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        if ($options) {
            $this->options = $options + $this->options;
        }

        self::$map = [
            // MP_BIN
            0xc4 => function () { return $this->unpackStr($this->unpackU8()); },
            0xc5 => function () { return $this->unpackStr($this->unpackU16()); },
            0xc6 => function () { return $this->unpackStr($this->unpackU32()); },

            0xca => [$this, 'unpackFloat'],
            0xcb => [$this, 'unpackDouble'],

            // MP_UINT
            0xcc => [$this, 'unpackU8'],
            0xcd => [$this, 'unpackU16'],
            0xce => [$this, 'unpackU32'],
            0xcf => [$this, 'unpackU64'],

            // MP_INT
            0xd0 => [$this, 'unpackI8'],
            0xd1 => [$this, 'unpackI16'],
            0xd2 => [$this, 'unpackI32'],
            0xd3 => [$this, 'unpackI64'],

            // MP_STR
            0xd9 => function () { return $this->unpackStr($this->unpackU8()); },
            0xda => function () { return $this->unpackStr($this->unpackU16()); },
            0xdb => function () { return $this->unpackStr($this->unpackU32()); },

            // MP_ARRAY
            0xdc => function () { return $this->unpackArr($this->unpackU16()); },
            0xdd => function () { return $this->unpackArr($this->unpackU32()); },

            // MP_MAP
            0xde => function () { return $this->unpackMap($this->unpackU16()); },
            0xdf => function () { return $this->unpackMap($this->unpackU32()); },

            // MP_EXT
            0xd4 => function () { return $this->unpackExt(1); },
            0xd5 => function () { return $this->unpackExt(2); },
            0xd6 => function () { return $this->unpackExt(4); },
            0xd7 => function () { return $this->unpackExt(8); },
            0xd8 => function () { return $this->unpackExt(16); },
            0xc7 => function () { return $this->unpackExt($this->unpackU8()); },
            0xc8 => function () { return $this->unpackExt($this->unpackU16()); },
            0xc9 => function () { return $this->unpackExt($this->unpackU32()); },
        ];
    }

    public function append($data)
    {
        $this->buffer .= $data;

        return $this;
    }

    public function reset($buffer = null)
    {
        $this->buffer = (string) $buffer;
        $this->offset = 0;

        return $this;
    }

    /**
     * @return array
     */
    public function tryUnpack()
    {
        $data = [];
        $offset = $this->offset;

        try {
            do {
                $data[] = $this->unpack();
                $offset = $this->offset;
            } while (isset($this->buffer[$this->offset]));
        } catch (InsufficientDataException $e) {
            $this->offset = $offset;
        }

        if ($this->offset) {
            $this->buffer = (string) substr($this->buffer, $this->offset);
            $this->offset = 0;
        }

        return $data;
    }

    public function unpack()
    {
        $this->ensureLength(1);

        $c = ord($this->buffer[$this->offset]);
        $this->offset += 1;

        // fixint
        if ($c <= 0x7f) {
            return $c;
        }
        // fixstr
        if ($c >= 0xa0 && $c <= 0xbf) {
            return $this->unpackStr($c & 0x1f);
        }
        // fixarray
        if ($c >= 0x90 && $c <= 0x9f) {
            return $this->unpackArr($c & 0xf);
        }
        // fixmap
        if ($c >= 0x80 && $c <= 0x8f) {
            return $this->unpackMap($c & 0xf);
        }
        // negfixint
        if ($c >= 0xe0) {
            return $c - 256;
        }
        if ($c === 0xc0) {
            return null;
        }
        if ($c === 0xc2) {
            return false;
        }
        if ($c === 0xc3) {
            return true;
        }
        if (!isset(self::$map[$c])) {
            throw new UnpackException(sprintf('Unknown code: 0x%x.', $c));
        }

        $func = self::$map[$c];

        return $func();
    }

    private function unpackU8()
    {
        $this->ensureLength(1);

        $num = $this->buffer[$this->offset];
        $this->offset += 1;

        $num = unpack('C', $num);

        return $num[1];
    }

    private function unpackU16()
    {
        $this->ensureLength(2);

        $num = $this->buffer[$this->offset].$this->buffer[$this->offset + 1];
        $this->offset += 2;

        $num = unpack('n', $num);

        return $num[1];
    }

    private function unpackU32()
    {
        $this->ensureLength(4);

        $num = substr($this->buffer, $this->offset, 4);
        $this->offset += 4;

        $num = unpack('N', $num);

        return $num[1];
    }

    private function unpackU64()
    {
        $this->ensureLength(8);

        $num = substr($this->buffer, $this->offset, 8);
        $this->offset += 8;

        //$num = unpack('J', $num);

        $set = unpack('N2', $num);
        $value = $set[1] << 32 | $set[2];

        // PHP does not support unsigned integers.
        // If a number is bigger than 2^63, it will be interpreted as a float.
        // @link http://php.net/manual/en/language.types.integer.php#language.types.integer.overflow

        return ($value < 0) ? $this->handleBigInt($value) : $value;
    }

    private function unpackI8()
    {
        $this->ensureLength(1);

        $num = $this->buffer[$this->offset];
        $this->offset += 1;

        $num = unpack('c', $num);

        return $num[1];
    }

    private function unpackI16()
    {
        $this->ensureLength(2);

        $num = $this->buffer[$this->offset].$this->buffer[$this->offset + 1];
        $this->offset += 2;

        $num = unpack('s', strrev($num));

        return $num[1];
    }

    private function unpackI32()
    {
        $this->ensureLength(4);

        $num = substr($this->buffer, $this->offset, 4);
        $this->offset += 4;

        $num = unpack('i', strrev($num));

        return $num[1];
    }

    private function unpackI64()
    {
        $this->ensureLength(8);

        $num = substr($this->buffer, $this->offset, 8);
        $this->offset += 8;

        $set = unpack('N2', $num);

        return $set[1] << 32 | $set[2];
    }

    private function unpackFloat()
    {
        $this->ensureLength(4);

        $num = substr($this->buffer, $this->offset, 4);
        $this->offset += 4;

        $num = unpack('f', strrev($num));

        return $num[1];
    }

    private function unpackDouble()
    {
        $this->ensureLength(8);

        $num = substr($this->buffer, $this->offset, 8);
        $this->offset += 8;

        $num = unpack('d', strrev($num));

        return $num[1];
    }

    private function unpackStr($length)
    {
        if (!$length) {
            return '';
        }

        $this->ensureLength($length);

        $str = substr($this->buffer, $this->offset, $length);
        $this->offset += $length;

        return $str;
    }

    private function unpackArr($size)
    {
        $array = [];

        for ($i = $size; $i; $i--) {
            $array[] = $this->unpack();
        }

        return $array;
    }

    /*
    private function unpackArrSpl($size)
    {
        $array = new \SplFixedArray($size);

        for ($i = 0; $i < $size; $i++) {
            $array[$i] = $this->unpack();
        }

        return $array;
    }
    */

    private function unpackMap($size)
    {
        $map = [];

        for ($i = $size; $i; $i--) {
            $key = $this->unpack();
            $value = $this->unpack();

            $map[$key] = $value;
        }

        return $map;
    }

    private function unpackExt($length)
    {
        $this->ensureLength($length);

        $type = $this->unpackI8();
        $data = substr($this->buffer, $this->offset, $length);
        $this->offset += $length;

        return new Ext($type, $data);
    }

    private function ensureLength($length)
    {
        if (!isset($this->buffer[$this->offset + $length - 1])) {
            throw new InsufficientDataException($length, strlen($this->buffer) - $this->offset);
        }
    }

    private function handleBigInt($value)
    {
        if (self::BIGINT_MODE_STR === $this->options[self::BIGINT_MODE]) {
            return sprintf('%u', $value);
        }

        if (self::BIGINT_MODE_GMP === $this->options[self::BIGINT_MODE]) {
            return gmp_init(sprintf('%u', $value));
        }

        throw new IntegerOverflowException($value);
    }
}
