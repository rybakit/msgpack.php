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

use MessagePack\Exception\PackingFailedException;
use MessagePack\TypeTransformer\Collection;

class Packer
{
    const FORCE_STR = 0b0001;
    const FORCE_BIN = 0b0010;
    const FORCE_ARR = 0b0100;
    const FORCE_MAP = 0b1000;

    private $strDetectionMode;
    private $arrDetectionMode;

    const NON_UTF8_REGEX = '/(
        [\xC0-\xC1] # Invalid UTF-8 Bytes
        | [\xF5-\xFF] # Invalid UTF-8 Bytes
        | \xE0[\x80-\x9F] # Overlong encoding of prior code point
        | \xF0[\x80-\x8F] # Overlong encoding of prior code point
        | [\xC2-\xDF](?![\x80-\xBF]) # Invalid UTF-8 Sequence Start
        | [\xE0-\xEF](?![\x80-\xBF]{2}) # Invalid UTF-8 Sequence Start
        | [\xF0-\xF4](?![\x80-\xBF]{3}) # Invalid UTF-8 Sequence Start
        | (?<=[\x0-\x7F\xF5-\xFF])[\x80-\xBF] # Invalid UTF-8 Sequence Middle
        | (?<![\xC2-\xDF]|[\xE0-\xEF]|[\xE0-\xEF][\x80-\xBF]|[\xF0-\xF4]|[\xF0-\xF4][\x80-\xBF]|[\xF0-\xF4][\x80-\xBF]{2})[\x80-\xBF] # Overlong Sequence
        | (?<=[\xE0-\xEF])[\x80-\xBF](?![\x80-\xBF]) # Short 3 byte sequence
        | (?<=[\xF0-\xF4])[\x80-\xBF](?![\x80-\xBF]{2}) # Short 4 byte sequence
        | (?<=[\xF0-\xF4][\x80-\xBF])[\x80-\xBF](?![\x80-\xBF]) # Short 4 byte sequence (2)
    )/x';

    /**
     * @var Collection
     */
    private $transformers;

    /**
     * @param int|null $typeDetectionMode
     */
    public function __construct($typeDetectionMode = null)
    {
        if (null !== $typeDetectionMode) {
            $this->setTypeDetectionMode($typeDetectionMode);
        }
    }

    /**
     * @param int $mode
     */
    public function setTypeDetectionMode($mode)
    {
        if ($mode > 0b1010 || $mode < 0 || 0b11 === ($mode & 0b11)) {
            throw new \InvalidArgumentException('Invalid type detection mode.');
        }

        $this->strDetectionMode = $mode & 0b0011;
        $this->arrDetectionMode = $mode & 0b1100;
    }

    /**
     * @param Collection|null $transformers
     */
    public function setTransformers(Collection $transformers = null)
    {
        $this->transformers = $transformers;
    }

    /**
     * @return Collection|null
     */
    public function getTransformers()
    {
        return $this->transformers;
    }

    public function pack($value)
    {
        if (\is_int($value)) {
            return $this->packInt($value);
        }
        if (\is_string($value)) {
            if (self::FORCE_STR === $this->strDetectionMode) {
                return $this->packStr($value);
            }
            if (self::FORCE_BIN === $this->strDetectionMode) {
                return $this->packBin($value);
            }
            return \preg_match(self::NON_UTF8_REGEX, $value)
                ? $this->packBin($value)
                : $this->packStr($value);
        }
        if (\is_array($value)) {
            if (self::FORCE_ARR === $this->arrDetectionMode) {
                return $this->packArray($value);
            }
            if (self::FORCE_MAP === $this->arrDetectionMode) {
                return $this->packMap($value);
            }
            return \array_values($value) === $value
                ? $this->packArray($value)
                : $this->packMap($value);
        }
        if (null === $value) {
            return $this->packNil();
        }
        if (\is_bool($value)) {
            return $this->packBool($value);
        }
        if (\is_double($value)) {
            return $this->packFloat($value);
        }
        if ($value instanceof Ext) {
            return $this->packExt($value);
        }

        if ($this->transformers && $transformer = $this->transformers->match($value)) {
            $ext = new Ext($transformer->getId(), $this->pack($transformer->transform($value)));

            return $this->packExt($ext);
        }

        throw new PackingFailedException($value, 'Unsupported type.');
    }

    public function packArray(array $array)
    {
        $size = \count($array);
        $data = self::packArrayHeader($size);

        foreach ($array as $val) {
            $data .= $this->pack($val);
        }

        return $data;
    }

    private static function packArrayHeader($size)
    {
        if ($size <= 0xf) {
            return \chr(0x90 | $size);
        }
        if ($size <= 0xffff) {
            return "\xdc".\chr($size >> 8).\chr($size);
        }

        return \pack('CN', 0xdd, $size);
    }

    public function packMap(array $map)
    {
        $size = \count($map);
        $data = self::packMapHeader($size);

        foreach ($map as $key => $val) {
            $data .= $this->pack($key);
            $data .= $this->pack($val);
        }

        return $data;
    }

    private static function packMapHeader($size)
    {
        if ($size <= 0xf) {
            return \chr(0x80 | $size);
        }
        if ($size <= 0xffff) {
            return "\xde".\chr($size >> 8).\chr($size);
        }

        return \pack('CN', 0xdf, $size);
    }

    public function packStr($str)
    {
        $len = \strlen($str);

        if ($len < 32) {
            return \chr(0xa0 | $len).$str;
        }
        if ($len <= 0xff) {
            return "\xd9".\chr($len).$str;
        }
        if ($len <= 0xffff) {
            return "\xda".\chr($len >> 8).\chr($len).$str;
        }

        return \pack('CN', 0xdb, $len).$str;
    }

    public function packBin($str)
    {
        $len = \strlen($str);

        if ($len <= 0xff) {
            return "\xc4".\chr($len).$str;
        }
        if ($len <= 0xffff) {
            return "\xc5".\chr($len >> 8).\chr($len).$str;
        }

        return \pack('CN', 0xc6, $len).$str;
    }

    public function packExt(Ext $ext)
    {
        $type = $ext->getType();
        $data = $ext->getData();
        $len = \strlen($data);

        switch ($len) {
            case 1: return "\xd4".\chr($type).$data;
            case 2: return "\xd5".\chr($type).$data;
            case 4: return "\xd6".\chr($type).$data;
            case 8: return "\xd7".\chr($type).$data;
            case 16: return "\xd8".\chr($type).$data;
        }

        if ($len <= 0xff) {
            return "\xc7".\chr($len).\chr($type).$data;
        }
        if ($len <= 0xffff) {
            return \pack('CnC', 0xc8, $len, $type).$data;
        }

        return \pack('CNC', 0xc9, $len, $type).$data;
    }

    public function packNil()
    {
        return "\xc0";
    }

    public function packBool($val)
    {
        return $val ? "\xc3" : "\xc2";
    }

    public function packFloat($num)
    {
        return "\xcb".strrev(pack('d', $num));
    }

    public function packInt($num)
    {
        if ($num >= 0) {
            if ($num <= 0x7f) {
                return \chr($num);
            }
            if ($num <= 0xff) {
                return "\xcc".\chr($num);
            }
            if ($num <= 0xffff) {
                return "\xcd".\chr($num >> 8).\chr($num);
            }
            if ($num <= 0xffffffff) {
                return \pack('CN', 0xce, $num);
            }

            return self::packUint64(0xcf, $num);
        }

        if ($num >= -0x20) {
            return \chr(0xe0 | $num);
        }
        if ($num >= -0x80) {
            return "\xd0".\chr($num);
        }
        if ($num >= -0x8000) {
            return "\xd1".\chr($num >> 8).\chr($num);
        }
        if ($num >= -0x80000000) {
            return \pack('CN', 0xd2, $num);
        }

        return self::packUint64(0xd3, $num);
    }

    private static function packUint64($code, $num)
    {
        $hi = ($num & 0xffffffff00000000) >> 32;
        $lo = $num & 0x00000000ffffffff;

        return \pack('CNN', $code, $hi, $lo);
    }
}
