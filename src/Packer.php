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
use MessagePack\TypeTransformer\Packable;

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
     * @var Packable[]|null
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

    public function registerTransformer(Packable $transformer)
    {
        $this->transformers[] = $transformer;
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
            return $this->packExt($value->type, $value->data);
        }
        if ($this->transformers) {
            foreach ($this->transformers as $transformer) {
                if (null !== $packed = $transformer->pack($this, $value)) {
                    return $packed;
                }
            }
        }

        throw new PackingFailedException($value, 'Unsupported type.');
    }

    public function packArray(array $array)
    {
        $data = $this->packArrayLength(\count($array));

        foreach ($array as $val) {
            $data .= $this->pack($val);
        }

        return $data;
    }

    public function packArrayLength($length)
    {
        if ($length <= 0xf) {
            return \chr(0x90 | $length);
        }
        if ($length <= 0xffff) {
            return "\xdc".\chr($length >> 8).\chr($length);
        }

        return \pack('CN', 0xdd, $length);
    }

    public function packMap(array $map)
    {
        $data = $this->packMapLength(\count($map));

        foreach ($map as $key => $val) {
            $data .= $this->pack($key);
            $data .= $this->pack($val);
        }

        return $data;
    }

    public function packMapLength($length)
    {
        if ($length <= 0xf) {
            return \chr(0x80 | $length);
        }
        if ($length <= 0xffff) {
            return "\xde".\chr($length >> 8).\chr($length);
        }

        return \pack('CN', 0xdf, $length);
    }

    public function packStr($str)
    {
        $length = \strlen($str);

        if ($length < 32) {
            return \chr(0xa0 | $length).$str;
        }
        if ($length <= 0xff) {
            return "\xd9".\chr($length).$str;
        }
        if ($length <= 0xffff) {
            return "\xda".\chr($length >> 8).\chr($length).$str;
        }

        return \pack('CN', 0xdb, $length).$str;
    }

    public function packBin($str)
    {
        $length = \strlen($str);

        if ($length <= 0xff) {
            return "\xc4".\chr($length).$str;
        }
        if ($length <= 0xffff) {
            return "\xc5".\chr($length >> 8).\chr($length).$str;
        }

        return \pack('CN', 0xc6, $length).$str;
    }

    public function packExt($type, $data)
    {
        $length = \strlen($data);

        switch ($length) {
            case 1: return "\xd4".\chr($type).$data;
            case 2: return "\xd5".\chr($type).$data;
            case 4: return "\xd6".\chr($type).$data;
            case 8: return "\xd7".\chr($type).$data;
            case 16: return "\xd8".\chr($type).$data;
        }

        if ($length <= 0xff) {
            return "\xc7".\chr($length).\chr($type).$data;
        }
        if ($length <= 0xffff) {
            return \pack('CnC', 0xc8, $length, $type).$data;
        }

        return \pack('CNC', 0xc9, $length, $type).$data;
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
