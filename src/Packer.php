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
    /**
     * @var string
     */
    private static $invalidUtf8Regex = '/(
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
        $type = \gettype($value);

        switch ($type) {
            case 'array': return \array_values($value) === $value
                ? $this->packArray($value)
                : $this->packMap($value);

            case 'string': return \preg_match(self::$invalidUtf8Regex, $value)
                ? $this->packBin($value)
                : $this->packStr($value);

            case 'integer': return $this->packInt($value);
            case 'NULL': return $this->packNil();
            case 'boolean': return $this->packBool($value);
            case 'double': return $this->packDouble($value);
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
            return \pack('Cn', 0xdc, $size);
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
            return \pack('Cn', 0xde, $size);
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
            return \pack('CC', 0xd9, $len).$str;
        }
        if ($len <= 0xffff) {
            return \pack('Cn', 0xda, $len).$str;
        }

        return \pack('CN', 0xdb, $len).$str;
    }

    public function packBin($str)
    {
        $len = \strlen($str);

        if ($len <= 0xff) {
            return \pack('CC', 0xc4, $len).$str;
        }
        if ($len <= 0xffff) {
            return \pack('Cn', 0xc5, $len).$str;
        }

        return \pack('CN', 0xc6, $len).$str;
    }

    public function packExt(Ext $ext)
    {
        $type = $ext->getType();
        $data = $ext->getData();
        $len = \strlen($data);

        switch ($len) {
            case 1: return \pack('CC', 0xd4, $type).$data;
            case 2: return \pack('CC', 0xd5, $type).$data;
            case 4: return \pack('CC', 0xd6, $type).$data;
            case 8: return \pack('CC', 0xd7, $type).$data;
            case 16: return \pack('CC', 0xd8, $type).$data;
        }

        if ($len <= 0xff) {
            return \pack('CCC', 0xc7, $len, $type).$data;
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

    public function packDouble($num)
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
                return \pack('CC', 0xcc, $num);
            }
            if ($num <= 0xffff) {
                return \pack('Cn', 0xcd, $num);
            }
            if ($num <= 0xffffffff) {
                return \pack('CN', 0xce, $num);
            }

            return self::packU64(0xcf, $num);
        }

        if ($num >= -0x20) {
            return \chr(0xe0 | $num);
        }
        if ($num >= -0x80) {
            return \pack('CC', 0xd0, $num);
        }
        if ($num >= -0x8000) {
            return \pack('Cn', 0xd1, $num);
        }
        if ($num >= -0x80000000) {
            return \pack('CN', 0xd2, $num);
        }

        return self::packU64(0xd3, $num);
    }

    private static function packU64($code, $num)
    {
        $hi = ($num & 0xffffffff00000000) >> 32;
        $lo = $num & 0x00000000ffffffff;

        return \pack('CNN', $code, $hi, $lo);
    }
}
