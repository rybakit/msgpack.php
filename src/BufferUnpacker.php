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
use MessagePack\Exception\InvalidCodeException;
use MessagePack\TypeTransformer\Extension;

class BufferUnpacker
{
    private $buffer;
    private $offset = 0;
    private $isBigIntAsStr;
    private $isBigIntAsGmp;

    /**
     * @var Extension[]|null
     */
    private $transformers;

    /**
     * @param string $buffer
     * @param UnpackOptions|int|null $options
     *
     * @throws \MessagePack\Exception\InvalidOptionException
     */
    public function __construct(string $buffer = '', $options = null)
    {
        if (null === $options) {
            $options = UnpackOptions::fromDefaults();
        } elseif (!$options instanceof PackOptions) {
            $options = UnpackOptions::fromBitmask($options);
        }

        $this->isBigIntAsStr = $options->isBigIntAsStrMode();
        $this->isBigIntAsGmp = $options->isBigIntAsGmpMode();

        $this->buffer = $buffer;
    }

    public function registerTransformer(Extension $transformer) : self
    {
        $this->transformers[$transformer->getType()] = $transformer;

        return $this;
    }

    public function append(string $data) : self
    {
        $this->buffer .= $data;

        return $this;
    }

    public function reset(string $buffer = '') : self
    {
        $this->buffer = $buffer;
        $this->offset = 0;

        return $this;
    }

    public function __clone()
    {
        $this->buffer = '';
        $this->offset = 0;
    }

    public function tryUnpack() : array
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
            $this->buffer = isset($this->buffer[$this->offset]) ? \substr($this->buffer, $this->offset) : '';
            $this->offset = 0;
        }

        return $data;
    }

    public function unpack()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $c = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        // fixint
        if ($c <= 0x7f) {
            return $c;
        }
        // fixstr
        if ($c >= 0xa0 && $c <= 0xbf) {
            return ($c & 0x1f) ? $this->unpackStrData($c & 0x1f) : '';
        }
        // fixarray
        if ($c >= 0x90 && $c <= 0x9f) {
            return ($c & 0xf) ? $this->unpackArrayData($c & 0xf) : [];
        }
        // fixmap
        if ($c >= 0x80 && $c <= 0x8f) {
            return ($c & 0xf) ? $this->unpackMapData($c & 0xf) : [];
        }
        // negfixint
        if ($c >= 0xe0) {
            return $c - 0x100;
        }

        switch ($c) {
            case 0xc0: return null;
            case 0xc2: return false;
            case 0xc3: return true;

            // bin
            case 0xc4: return $this->unpackStrData($this->unpackUint8());
            case 0xc5: return $this->unpackStrData($this->unpackUint16());
            case 0xc6: return $this->unpackStrData($this->unpackUint32());

            // float
            case 0xca: return $this->unpackFloat32();
            case 0xcb: return $this->unpackFloat64();

            // uint
            case 0xcc: return $this->unpackUint8();
            case 0xcd: return $this->unpackUint16();
            case 0xce: return $this->unpackUint32();
            case 0xcf: return $this->unpackUint64();

            // int
            case 0xd0: return $this->unpackInt8();
            case 0xd1: return $this->unpackInt16();
            case 0xd2: return $this->unpackInt32();
            case 0xd3: return $this->unpackInt64();

            // str
            case 0xd9: return $this->unpackStrData($this->unpackUint8());
            case 0xda: return $this->unpackStrData($this->unpackUint16());
            case 0xdb: return $this->unpackStrData($this->unpackUint32());

            // array
            case 0xdc: return $this->unpackArrayData($this->unpackUint16());
            case 0xdd: return $this->unpackArrayData($this->unpackUint32());

            // map
            case 0xde: return $this->unpackMapData($this->unpackUint16());
            case 0xdf: return $this->unpackMapData($this->unpackUint32());

            // ext
            case 0xd4: return $this->unpackExtData(1);
            case 0xd5: return $this->unpackExtData(2);
            case 0xd6: return $this->unpackExtData(4);
            case 0xd7: return $this->unpackExtData(8);
            case 0xd8: return $this->unpackExtData(16);
            case 0xc7: return $this->unpackExtData($this->unpackUint8());
            case 0xc8: return $this->unpackExtData($this->unpackUint16());
            case 0xc9: return $this->unpackExtData($this->unpackUint32());
        }

        throw InvalidCodeException::fromUnknownCode($c);
    }

    public function unpackNil()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        if ("\xc0" === $this->buffer[$this->offset]) {
            ++$this->offset;

            return null;
        }

        throw InvalidCodeException::fromExpectedType('nil', \ord($this->buffer[$this->offset++]));
    }

    public function unpackBool()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $c = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        if (0xc2 === $c) {
            return false;
        }
        if (0xc3 === $c) {
            return true;
        }

        throw InvalidCodeException::fromExpectedType('bool', $c);
    }

    public function unpackInt()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $c = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        // fixint
        if ($c <= 0x7f) {
            return $c;
        }
        // negfixint
        if ($c >= 0xe0) {
            return $c - 0x100;
        }

        switch ($c) {
            // uint
            case 0xcc: return $this->unpackUint8();
            case 0xcd: return $this->unpackUint16();
            case 0xce: return $this->unpackUint32();
            case 0xcf: return $this->unpackUint64();

            // int
            case 0xd0: return $this->unpackInt8();
            case 0xd1: return $this->unpackInt16();
            case 0xd2: return $this->unpackInt32();
            case 0xd3: return $this->unpackInt64();
        }

        throw InvalidCodeException::fromExpectedType('int', $c);
    }

    public function unpackFloat()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $c = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        if (0xcb === $c) {
            return $this->unpackFloat64();
        }
        if (0xca === $c) {
            return $this->unpackFloat32();
        }

        throw InvalidCodeException::fromExpectedType('float', $c);
    }

    public function unpackStr()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $c = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        if ($c >= 0xa0 && $c <= 0xbf) {
            return ($c & 0x1f) ? $this->unpackStrData($c & 0x1f) : '';
        }
        if (0xd9 === $c) {
            return $this->unpackStrData($this->unpackUint8());
        }
        if (0xda === $c) {
            return $this->unpackStrData($this->unpackUint16());
        }
        if (0xdb === $c) {
            return $this->unpackStrData($this->unpackUint32());
        }

        throw InvalidCodeException::fromExpectedType('str', $c);
    }

    public function unpackBin()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $c = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        if (0xc4 === $c) {
            return $this->unpackStrData($this->unpackUint8());
        }
        if (0xc5 === $c) {
            return $this->unpackStrData($this->unpackUint16());
        }
        if (0xc6 === $c) {
            return $this->unpackStrData($this->unpackUint32());
        }

        throw InvalidCodeException::fromExpectedType('bin', $c);
    }

    public function unpackArray()
    {
        $size = $this->unpackArrayHeader();

        $array = [];
        while ($size--) {
            $array[] = $this->unpack();
        }

        return $array;
    }

    public function unpackArrayHeader()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $c = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        if ($c >= 0x90 && $c <= 0x9f) {
            return $c & 0xf;
        }
        if (0xdc === $c) {
            return $this->unpackUint16();
        }
        if (0xdd === $c) {
            return $this->unpackUint32();
        }

        throw InvalidCodeException::fromExpectedType('array header', $c);
    }

    public function unpackMap()
    {
        $size = $this->unpackMapHeader();

        $map = [];
        while ($size--) {
            $map[$this->unpack()] = $this->unpack();
        }

        return $map;
    }

    public function unpackMapHeader()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $c = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        if ($c >= 0x80 && $c <= 0x8f) {
            return $c & 0xf;
        }
        if (0xde === $c) {
            return $this->unpackUint16();
        }
        if (0xdf === $c) {
            return $this->unpackUint32();
        }

        throw InvalidCodeException::fromExpectedType('map header', $c);
    }

    public function unpackExt()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $c = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        switch ($c) {
            case 0xd4: return $this->unpackExtData(1);
            case 0xd5: return $this->unpackExtData(2);
            case 0xd6: return $this->unpackExtData(4);
            case 0xd7: return $this->unpackExtData(8);
            case 0xd8: return $this->unpackExtData(16);
            case 0xc7: return $this->unpackExtData($this->unpackUint8());
            case 0xc8: return $this->unpackExtData($this->unpackUint16());
            case 0xc9: return $this->unpackExtData($this->unpackUint32());
        }

        throw InvalidCodeException::fromExpectedType('ext header', $c);
    }

    private function unpackUint8()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        return \ord($this->buffer[$this->offset++]);
    }

    private function unpackUint16()
    {
        if (!isset($this->buffer[$this->offset + 1])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 2);
        }

        $hi = \ord($this->buffer[$this->offset]);
        $lo = \ord($this->buffer[$this->offset + 1]);
        $this->offset += 2;

        return $hi << 8 | $lo;
    }

    private function unpackUint32()
    {
        if (!isset($this->buffer[$this->offset + 3])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 4);
        }

        $num = \unpack('N', $this->buffer, $this->offset)[1];
        $this->offset += 4;

        return $num;
    }

    private function unpackUint64()
    {
        if (!isset($this->buffer[$this->offset + 7])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 8);
        }

        $num = \unpack('J', $this->buffer, $this->offset)[1];
        $this->offset += 8;

        return $num < 0 ? $this->handleIntOverflow($num) : $num;
    }

    private function unpackInt8()
    {
        if (!isset($this->buffer[$this->offset])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 1);
        }

        $num = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        return $num > 0x7f ? $num - 0x100 : $num;
    }

    private function unpackInt16()
    {
        if (!isset($this->buffer[$this->offset + 1])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 2);
        }

        $hi = \ord($this->buffer[$this->offset]);
        $lo = \ord($this->buffer[$this->offset + 1]);
        $this->offset += 2;

        return $hi > 0x7f ? $hi << 8 | $lo - 0x10000 : $hi << 8 | $lo;
    }

    private function unpackInt32()
    {
        if (!isset($this->buffer[$this->offset + 3])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 4);
        }

        $num = \unpack('N', $this->buffer, $this->offset)[1];
        $this->offset += 4;

        return $num > 0x7fffffff ? $num - 0x100000000 : $num;
    }

    private function unpackInt64()
    {
        if (!isset($this->buffer[$this->offset + 7])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 8);
        }

        $num = \unpack('J', $this->buffer, $this->offset)[1];
        $this->offset += 8;

        return $num;
    }

    private function unpackFloat32()
    {
        if (!isset($this->buffer[$this->offset + 3])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 4);
        }

        $num = \unpack('G', $this->buffer, $this->offset)[1];
        $this->offset += 4;

        return $num;
    }

    private function unpackFloat64()
    {
        if (!isset($this->buffer[$this->offset + 7])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, 8);
        }

        $num = \unpack('E', $this->buffer, $this->offset)[1];
        $this->offset += 8;

        return $num;
    }

    private function unpackStrData($length)
    {
        if (!isset($this->buffer[$this->offset + $length - 1])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, $length);
        }

        $str = \substr($this->buffer, $this->offset, $length);
        $this->offset += $length;

        return $str;
    }

    private function unpackArrayData($size)
    {
        $array = [];
        while ($size--) {
            $array[] = $this->unpack();
        }

        return $array;
    }

    private function unpackMapData($size)
    {
        $map = [];
        while ($size--) {
            $map[$this->unpack()] = $this->unpack();
        }

        return $map;
    }

    private function unpackExtData($length)
    {
        if (!isset($this->buffer[$this->offset + $length - 1])) {
            throw InsufficientDataException::fromOffset($this->buffer, $this->offset, $length);
        }

        // int8
        $num = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        $type = $num > 0x7f ? $num - 0x100 : $num;

        if (isset($this->transformers[$type])) {
            return $this->transformers[$type]->unpack($this, $length);
        }

        $data = \substr($this->buffer, $this->offset, $length);
        $this->offset += $length;

        return new Ext($type, $data);
    }

    private function handleIntOverflow($value)
    {
        if ($this->isBigIntAsStr) {
            return \sprintf('%u', $value);
        }
        if ($this->isBigIntAsGmp) {
            return \gmp_init(\sprintf('%u', $value));
        }

        throw new IntegerOverflowException($value);
    }
}
