<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack;

use Decimal\Decimal;
use MessagePack\Exception\InsufficientDataException;
use MessagePack\Exception\InvalidOptionException;
use MessagePack\Exception\UnpackingFailedException;
use MessagePack\TypeTransformer\Extension;

class BufferUnpacker
{
    private $buffer;
    private $offset = 0;
    private $isBigIntAsDec;
    private $isBigIntAsGmp;

    /**
     * @var Extension[]
     */
    private $extensions = [];

    /**
     * @param UnpackOptions|int|null $options
     * @param Extension[] $extensions
     *
     * @throws InvalidOptionException
     */
    public function __construct(string $buffer = '', $options = null, array $extensions = [])
    {
        if (\is_null($options)) {
            $options = UnpackOptions::fromDefaults();
        } elseif (!$options instanceof UnpackOptions) {
            $options = UnpackOptions::fromBitmask($options);
        }

        $this->isBigIntAsDec = $options->isBigIntAsDecMode();
        $this->isBigIntAsGmp = $options->isBigIntAsGmpMode();

        $this->buffer = $buffer;

        if ($extensions) {
            foreach ($extensions as $extension) {
                $this->extensions[$extension->getType()] = $extension;
            }
        }
    }

    public function extendWith(Extension $extension, Extension ...$extensions) : self
    {
        $new = clone $this;
        $new->extensions[$extension->getType()] = $extension;

        if ($extensions) {
            foreach ($extensions as $extraExtension) {
                $new->extensions[$extraExtension->getType()] = $extraExtension;
            }
        }

        return $new;
    }

    public function withBuffer(string $buffer) : self
    {
        $new = clone $this;
        $new->buffer = $buffer;
        $new->offset = 0;

        return $new;
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

    public function seek(int $offset) : self
    {
        if ($offset < 0) {
            $offset += \strlen($this->buffer);
        }

        if (!isset($this->buffer[$offset])) {
            throw new InsufficientDataException("Unable to seek to position $offset");
        }

        $this->offset = $offset;

        return $this;
    }

    public function skip(int $length) : self
    {
        $offset = $this->offset + $length;

        if (!isset($this->buffer[$offset])) {
            throw new InsufficientDataException("Unable to seek to position $offset");
        }

        $this->offset = $offset;

        return $this;
    }

    public function getRemainingCount() : int
    {
        return \strlen($this->buffer) - $this->offset;
    }

    public function hasRemaining() : bool
    {
        return isset($this->buffer[$this->offset]);
    }

    public function release() : int
    {
        if (0 === $this->offset) {
            return 0;
        }

        $releasedBytesCount = $this->offset;
        $this->buffer = isset($this->buffer[$this->offset]) ? \substr($this->buffer, $this->offset) : '';
        $this->offset = 0;

        return $releasedBytesCount;
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public function read($length)
    {
        if (!isset($this->buffer[$this->offset + $length - 1])) {
            throw new InsufficientDataException();
        }

        $data = \substr($this->buffer, $this->offset, $length);
        $this->offset += $length;

        return $data;
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

        return $data;
    }

    public function unpack()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;
        $stack = [];
        $top = 0;

        begin:
        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $c = \ord($buffer[$offset]);
        ++$offset;

        // fixint
        if ($c <= 0x7f) {
            $value = $c;
            goto end;
        }

        // fixstr
        if ($c >= 0xa0 && $c <= 0xbf) {
            if ($c & 0x1f) {
                $length = $c & 0x1f;
                goto read;
            }
            $value = '';
            goto end;
        }

        // negfixint
        if ($c >= 0xe0) {
            $value = $c - 0x100;
            goto end;
        }

        switch ($c) {
            case 0xc0: $value = null; goto end;
            case 0xc2: $value = false; goto end;
            case 0xc3: $value = true; goto end;

            // fixmap
            case 0x80: $value = []; goto end;
            case 0x81: $stack[++$top] = new Container(2 /* map key */, 1); goto begin;
            case 0x82: $stack[++$top] = new Container(2 /* map key */, 2); goto begin;
            case 0x83: $stack[++$top] = new Container(2 /* map key */, 3); goto begin;
            case 0x84: $stack[++$top] = new Container(2 /* map key */, 4); goto begin;
            case 0x85: $stack[++$top] = new Container(2 /* map key */, 5); goto begin;
            case 0x86: $stack[++$top] = new Container(2 /* map key */, 6); goto begin;
            case 0x87: $stack[++$top] = new Container(2 /* map key */, 7); goto begin;
            case 0x88: $stack[++$top] = new Container(2 /* map key */, 8); goto begin;
            case 0x89: $stack[++$top] = new Container(2 /* map key */, 9); goto begin;
            case 0x8a: $stack[++$top] = new Container(2 /* map key */, 10); goto begin;
            case 0x8b: $stack[++$top] = new Container(2 /* map key */, 11); goto begin;
            case 0x8c: $stack[++$top] = new Container(2 /* map key */, 12); goto begin;
            case 0x8d: $stack[++$top] = new Container(2 /* map key */, 13); goto begin;
            case 0x8e: $stack[++$top] = new Container(2 /* map key */, 14); goto begin;
            case 0x8f: $stack[++$top] = new Container(2 /* map key */, 15); goto begin;

            // fixarray
            case 0x90: $value = []; goto end;
            case 0x91: $stack[++$top] = new Container(1 /* array item */, 1); goto begin;
            case 0x92: $stack[++$top] = new Container(1 /* array item */, 2); goto begin;
            case 0x93: $stack[++$top] = new Container(1 /* array item */, 3); goto begin;
            case 0x94: $stack[++$top] = new Container(1 /* array item */, 4); goto begin;
            case 0x95: $stack[++$top] = new Container(1 /* array item */, 5); goto begin;
            case 0x96: $stack[++$top] = new Container(1 /* array item */, 6); goto begin;
            case 0x97: $stack[++$top] = new Container(1 /* array item */, 7); goto begin;
            case 0x98: $stack[++$top] = new Container(1 /* array item */, 8); goto begin;
            case 0x99: $stack[++$top] = new Container(1 /* array item */, 9); goto begin;
            case 0x9a: $stack[++$top] = new Container(1 /* array item */, 10); goto begin;
            case 0x9b: $stack[++$top] = new Container(1 /* array item */, 11); goto begin;
            case 0x9c: $stack[++$top] = new Container(1 /* array item */, 12); goto begin;
            case 0x9d: $stack[++$top] = new Container(1 /* array item */, 13); goto begin;
            case 0x9e: $stack[++$top] = new Container(1 /* array item */, 14); goto begin;
            case 0x9f: $stack[++$top] = new Container(1 /* array item */, 15); goto begin;

            // bin
            case 0xc4: $length = self::unpackUint8($buffer, $offset); goto read;
            case 0xc5: $length = self::unpackUint16($buffer, $offset); goto read;
            case 0xc6: $length = self::unpackUint32($buffer, $offset); goto read;

            // float
            case 0xca: $value = self::unpackFloat32($buffer, $offset); goto end;
            case 0xcb: $value = self::unpackFloat64($buffer, $offset); goto end;

            // uint
            case 0xcc: $value = self::unpackUint8($buffer, $offset); goto end;
            case 0xcd: $value = self::unpackUint16($buffer, $offset); goto end;
            case 0xce: $value = self::unpackUint32($buffer, $offset); goto end;
            case 0xcf: $value = $this->unpackUint64(); goto end;

            // int
            case 0xd0: $value = self::unpackInt8($buffer, $offset); goto end;
            case 0xd1: $value = self::unpackInt16($buffer, $offset); goto end;
            case 0xd2: $value = self::unpackInt32($buffer, $offset); goto end;
            case 0xd3: $value = self::unpackInt64($buffer, $offset); goto end;

            // str
            case 0xd9: $length = self::unpackUint8($buffer, $offset); goto read;
            case 0xda: $length = self::unpackUint16($buffer, $offset); goto read;
            case 0xdb: $length = self::unpackUint32($buffer, $offset); goto read;

            // array
            case 0xdc: $stack[++$top] = new Container(1 /* array item */, self::unpackUint16($buffer, $offset)); goto begin;
            case 0xdd: $stack[++$top] = new Container(1 /* array item */, self::unpackUint32($buffer, $offset)); goto begin;

            // map
            case 0xde: $stack[++$top] = new Container(2 /* map key */, self::unpackUint16($buffer, $offset)); goto begin;
            case 0xdf: $stack[++$top] = new Container(2 /* map key */, self::unpackUint32($buffer, $offset)); goto begin;

            // ext
            case 0xd4: $value = $this->unpackExtData(1); goto end;
            case 0xd5: $value = $this->unpackExtData(2); goto end;
            case 0xd6: $value = $this->unpackExtData(4); goto end;
            case 0xd7: $value = $this->unpackExtData(8); goto end;
            case 0xd8: $value = $this->unpackExtData(16); goto end;
            case 0xc7: $value = $this->unpackExtData(self::unpackUint8($buffer, $offset)); goto end;
            case 0xc8: $value = $this->unpackExtData(self::unpackUint16($buffer, $offset)); goto end;
            case 0xc9: $value = $this->unpackExtData(self::unpackUint32($buffer, $offset)); goto end;
        }

        throw UnpackingFailedException::unknownCode($c);

        read:
        if (!isset($buffer[$offset + $length - 1])) {
            throw new InsufficientDataException();
        }

        $value = \substr($buffer, $offset, $length);
        $offset += $length;

        end:
        while ($stack) {
            /** @var Container $state */
            $state = $stack[$top];
            if ($state->type === 1 /* array item */) {
                $state->value[] = $value;
                ++$state->count;
            } else if ($state->type === 2 /* map key */) {
                $state->type = 3 /* map value */;
                $state->key = $value;
                goto begin;
            } else if ($state->type === 3 /* map value */) {
                $state->type = 2 /* map key */;
                $state->value[$state->key] = $value;
                ++$state->count;
            }

            if ($state->size !== $state->count) {
                goto begin;
            }

            unset($stack[$top]);
            --$top;
            $value = $state->value;
        }

        return $value;
    }

    public function unpackNil()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        if ("\xc0" === $buffer[$offset]) {
            ++$offset;

            return null;
        }

        throw UnpackingFailedException::unexpectedCode(\ord($buffer[$offset++]), 'nil');
    }

    public function unpackBool()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $c = \ord($buffer[$offset]);
        ++$offset;

        if (0xc2 === $c) {
            return false;
        }
        if (0xc3 === $c) {
            return true;
        }

        throw UnpackingFailedException::unexpectedCode($c, 'bool');
    }

    public function unpackInt()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $c = \ord($buffer[$offset]);
        ++$offset;

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
            case 0xcc: return self::unpackUint8($buffer, $offset);
            case 0xcd: return self::unpackUint16($buffer, $offset);
            case 0xce: return self::unpackUint32($buffer, $offset);
            case 0xcf: return $this->unpackUint64();
            // int
            case 0xd0: return self::unpackInt8($buffer, $offset);
            case 0xd1: return self::unpackInt16($buffer, $offset);
            case 0xd2: return self::unpackInt32($buffer, $offset);
            case 0xd3: return self::unpackInt64($buffer, $offset);
        }

        throw UnpackingFailedException::unexpectedCode($c, 'int');
    }

    public function unpackFloat()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $c = \ord($buffer[$offset]);
        ++$offset;

        if (0xcb === $c) {
            return self::unpackFloat64($buffer, $offset);
        }
        if (0xca === $c) {
            return self::unpackFloat32($buffer, $offset);
        }

        throw UnpackingFailedException::unexpectedCode($c, 'float');
    }

    public function unpackStr()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $c = \ord($buffer[$offset]);
        ++$offset;

        if ($c >= 0xa0 && $c <= 0xbf) {
            return ($c & 0x1f) ? $this->read($c & 0x1f) : '';
        }
        if (0xd9 === $c) {
            return $this->read(self::unpackUint8($buffer, $offset));
        }
        if (0xda === $c) {
            return $this->read(self::unpackUint16($buffer, $offset));
        }
        if (0xdb === $c) {
            return $this->read(self::unpackUint32($buffer, $offset));
        }

        throw UnpackingFailedException::unexpectedCode($c, 'str');
    }

    public function unpackBin()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $c = \ord($buffer[$offset]);
        ++$offset;

        if (0xc4 === $c) {
            return $this->read(self::unpackUint8($buffer, $offset));
        }
        if (0xc5 === $c) {
            return $this->read(self::unpackUint16($buffer, $offset));
        }
        if (0xc6 === $c) {
            return $this->read(self::unpackUint32($buffer, $offset));
        }

        throw UnpackingFailedException::unexpectedCode($c, 'bin');
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
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $c = \ord($buffer[$offset]);
        ++$offset;

        if ($c >= 0x90 && $c <= 0x9f) {
            return $c & 0xf;
        }
        if (0xdc === $c) {
            return self::unpackUint16($buffer, $offset);
        }
        if (0xdd === $c) {
            return self::unpackUint32($buffer, $offset);
        }

        throw UnpackingFailedException::unexpectedCode($c, 'array');
    }

    public function unpackMap()
    {
        $size = $this->unpackMapHeader();

        $map = [];
        while ($size--) {
            //$map[$this->unpackMapKey()] = $this->unpack();
            $map[$this->unpack()] = $this->unpack();
        }

        return $map;
    }

    public function unpackMapHeader()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $c = \ord($buffer[$offset]);
        ++$offset;

        if ($c >= 0x80 && $c <= 0x8f) {
            return $c & 0xf;
        }
        if (0xde === $c) {
            return self::unpackUint16($buffer, $offset);
        }
        if (0xdf === $c) {
            return self::unpackUint32($buffer, $offset);
        }

        throw UnpackingFailedException::unexpectedCode($c, 'map');
    }

    public function unpackExt()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $c = \ord($buffer[$offset]);
        ++$offset;

        switch ($c) {
            case 0xd4: return $this->unpackExtData(1);
            case 0xd5: return $this->unpackExtData(2);
            case 0xd6: return $this->unpackExtData(4);
            case 0xd7: return $this->unpackExtData(8);
            case 0xd8: return $this->unpackExtData(16);
            case 0xc7: return $this->unpackExtData(self::unpackUint8($buffer, $offset));
            case 0xc8: return $this->unpackExtData(self::unpackUint16($buffer, $offset));
            case 0xc9: return $this->unpackExtData(self::unpackUint32($buffer, $offset));
        }

        throw UnpackingFailedException::unexpectedCode($c, 'ext');
    }

    private static function unpackUint8($buffer, &$offset)
    {
        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        return \ord($buffer[$offset++]);
    }

    private static function unpackUint16($buffer, &$offset)
    {
        if (!isset($buffer[$offset + 1])) {
            throw new InsufficientDataException();
        }

        return \ord($buffer[$offset++]) << 8
            | \ord($buffer[$offset++]);
    }

    private static function unpackUint32($buffer, &$offset)
    {
        if (!isset($buffer[$offset + 3])) {
            throw new InsufficientDataException();
        }

        return \ord($buffer[$offset++]) << 24
            | \ord($buffer[$offset++]) << 16
            | \ord($buffer[$offset++]) << 8
            | \ord($buffer[$offset++]);
    }

    private function unpackUint64()
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset + 7])) {
            throw new InsufficientDataException();
        }

        $num = \unpack('J', $buffer, $offset)[1];
        $offset += 8;

        if ($num >= 0) {
            return $num;
        }
        if ($this->isBigIntAsDec) {
            return new Decimal(\sprintf('%u', $num));
        }
        if ($this->isBigIntAsGmp) {
            return \gmp_import(\substr($buffer, $offset - 8, 8));
        }

        return \sprintf('%u', $num);
    }

    private static function unpackInt8($buffer, &$offset)
    {
        if (!isset($buffer[$offset])) {
            throw new InsufficientDataException();
        }

        $num = \ord($buffer[$offset]);
        ++$offset;

        return $num > 0x7f ? $num - 0x100 : $num;
    }

    private static function unpackInt16($buffer, &$offset)
    {
        if (!isset($buffer[$offset + 1])) {
            throw new InsufficientDataException();
        }

        $num = \ord($buffer[$offset]) << 8
            | \ord($buffer[++$offset]);
        ++$offset;

        return $num > 0x7fff ? $num - 0x10000 : $num;
    }

    private static function unpackInt32($buffer, &$offset)
    {
        if (!isset($buffer[$offset + 3])) {
            throw new InsufficientDataException();
        }

        $num = \ord($buffer[$offset]) << 24
            | \ord($buffer[++$offset]) << 16
            | \ord($buffer[++$offset]) << 8
            | \ord($buffer[++$offset]);
        ++$offset;

        return $num > 0x7fffffff ? $num - 0x100000000 : $num;
    }

    private static function unpackInt64($buffer, &$offset)
    {
        if (!isset($buffer[$offset + 7])) {
            throw new InsufficientDataException();
        }

        $num = \unpack('J', $buffer, $offset)[1];
        $offset += 8;

        return $num;
    }

    private static function unpackFloat32($buffer, &$offset)
    {
        if (!isset($buffer[$offset + 3])) {
            throw new InsufficientDataException();
        }

        $num = \unpack('G', $buffer, $offset)[1];
        $offset += 4;

        return $num;
    }

    private static function unpackFloat64($buffer, &$offset)
    {
        if (!isset($buffer[$offset + 7])) {
            throw new InsufficientDataException();
        }

        $num = \unpack('E', $buffer, $offset)[1];
        $offset += 8;

        return $num;
    }

    private function unpackExtData($length)
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset + $length])) { // 1 (type) + length - 1
            throw new InsufficientDataException();
        }

        // int8
        $type = \ord($buffer[$offset]);
        ++$offset;

        if ($type > 0x7f) {
            $type -= 0x100;
        }

        if (isset($this->extensions[$type])) {
            return $this->extensions[$type]->unpackExt($this, $length);
        }

        $data = \substr($buffer, $offset, $length);
        $offset += $length;

        return new Ext($type, $data);
    }
}

final class Container
{
    public $type;
    public $size;
    public $count = 0;
    public $value;
    public $key;

    public function __construct($type, $size)
    {
        $this->type = $type;
        $this->size = $size;
    }
}
