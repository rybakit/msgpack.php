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

use MessagePack\Exception\InvalidOptionException;
use MessagePack\Exception\PackingFailedException;
use MessagePack\TypeTransformer\Collection;

class Packer
{
    const UTF8_REGEX = '/\A(?:
          [\x00-\x7F]++                      # ASCII
        | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
        |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
        |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
        |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
        )*+\z/x';

    private $isDetectStrBin;
    private $isForceStr;
    private $isDetectArrMap;
    private $isForceArr;
    private $isForceFloat32;

    /**
     * @var Collection|null
     */
    private $transformers;

    /**
     * @param PackOptions|int|null $options
     *
     * @throws InvalidOptionException
     */
    public function __construct($options = null)
    {
        if (!$options instanceof PackOptions) {
            $options = PackOptions::fromBitmask($options);
        }

        $this->isDetectStrBin = $options->isDetectStrBinMode();
        $this->isForceStr = $options->isForceStrMode();
        $this->isDetectArrMap = $options->isDetectArrMapMode();
        $this->isForceArr = $options->isForceArrMode();
        $this->isForceFloat32 = $options->isForceFloat32Mode();
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
            if ($this->isDetectStrBin) {
                return \preg_match(self::UTF8_REGEX, $value)
                    ? $this->packStr($value)
                    : $this->packBin($value);
            }

            return $this->isForceStr ? $this->packStr($value) : $this->packBin($value);
        }
        if (\is_array($value)) {
            if ($this->isDetectArrMap) {
                return \array_values($value) === $value
                    ? $this->packArray($value)
                    : $this->packMap($value);
            }

            return $this->isForceArr ? $this->packArray($value) : $this->packMap($value);
        }
        if (null === $value) {
            return $this->packNil();
        }
        if (\is_bool($value)) {
            return $this->packBool($value);
        }
        if (\is_float($value)) {
            return $this->isForceFloat32
                ? $this->packFloat32($value)
                : $this->packFloat64($value);
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
        $len = \strlen($ext->data);

        switch ($len) {
            case 1: return "\xd4".\chr($ext->type).$ext->data;
            case 2: return "\xd5".\chr($ext->type).$ext->data;
            case 4: return "\xd6".\chr($ext->type).$ext->data;
            case 8: return "\xd7".\chr($ext->type).$ext->data;
            case 16: return "\xd8".\chr($ext->type).$ext->data;
        }

        if ($len <= 0xff) {
            return "\xc7".\chr($len).\chr($ext->type).$ext->data;
        }
        if ($len <= 0xffff) {
            return \pack('CnC', 0xc8, $len, $ext->type).$ext->data;
        }

        return \pack('CNC', 0xc9, $len, $ext->type).$ext->data;
    }

    public function packNil()
    {
        return "\xc0";
    }

    public function packBool($val)
    {
        return $val ? "\xc3" : "\xc2";
    }

    public function packFloat32($num)
    {
        return "\xca".\strrev(\pack('f', $num));
    }

    public function packFloat64($num)
    {
        return "\xcb".\strrev(\pack('d', $num));
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
