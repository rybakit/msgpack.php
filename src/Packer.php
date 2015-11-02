<?php

namespace MessagePack;

use MessagePack\Exception\PackException;

class Packer
{
    const FORCE_MAP = 1;
    const FORCE_ARR = 2;
    const FORCE_BIN = 4;
    const FORCE_UTF8 = 8;

    private $opts = 0;

    public function __construct($opts = 0)
    {
        $this->opts = $opts;
    }

    public function pack($value, $opts = 0)
    {
        $type = gettype($value);

        switch ($type) {
            case 'array':
                if (self::FORCE_ARR & $opts) {
                    return $this->packArr($value, $opts);
                }
                if (self::FORCE_MAP & $opts) {
                    return $this->packMap($value, $opts);
                }
                if (array_values($value) === $value) {
                    return $this->packArr($value, $opts);
                }

                return $this->packMap($value, $opts);
            case 'string':
                if (self::FORCE_UTF8 & $opts) {
                    return self::packStr($value);
                }
                if (self::FORCE_BIN & $opts) {
                    return self::packBin($value);
                }

                return preg_match('//u', $value) ? self::packStr($value) : self::packBin($value);
            case 'boolean':
                return self::packBool($value);
            case 'integer':
                return self::packInt($value);
            case 'double':
                return self::packDouble($value);
            case 'NULL':
                return self::packNil();
        }

        if ($value instanceof Ext) {
            return self::packExt($value);
        }

        throw new PackException($value, 'Unsupported type.');
    }

    private function packArr(array $array, $opts = 0)
    {
        $size = count($array);
        $data = self::packArrHeader($size);

        foreach ($array as $val) {
            $data .= $this->pack($val, $opts);
        }

        return $data;
    }

    private static function packArrHeader($size)
    {
        if ($size <= 0xf) {
            return self::packFix(0x90, $size);
        }

        if ($size <= 0xffff) {
            return self::packU16(0xdc, $size);
        }

        return self::packU32(0xdd, $size);
    }

    private function packMap(array $map, $opts = 0)
    {
        $size = count($map);
        $data = self::packMapHeader($size);

        foreach ($map as $key => $val) {
            $data .= $this->pack($key, $opts);
            $data .= $this->pack($val, $opts);
        }

        return $data;
    }

    private static function packMapHeader($size)
    {
        if ($size <= 0xf) {
            return self::packFix(0x80, $size);
        }

        if ($size <= 0xffff) {
            return self::packU16(0xde, $size);
        }

        return self::packU32(0xdf, $size);
    }

    private static function packBool($val)
    {
        return $val ? "\xc3" : "\xc2";
    }

    private static function packNil()
    {
        return "\xc0";
    }

    private static function packExt(Ext $ext)
    {
        $type = $ext->getType();
        $data = $ext->getData();
        $len = strlen($data);

        if (1 === $len) {
            return pack('CC', 0xd4, $type).$data;
        }
        if (2 === $len) {
            return pack('CC', 0xd5, $type).$data;
        }
        if (4 === $len) {
            return pack('CC', 0xd6, $type).$data;
        }
        if (8 === $len) {
            return pack('CC', 0xd7, $type).$data;
        }
        if (16 === $len) {
            return pack('CC', 0xd8, $type).$data;
        }
        if ($len <= 0xff) {
            return pack('CCC', 0xc7, $len, $type).$data;
        }
        if ($len <= 0xffff) {
            return pack('CnC', 0xc8, $len, $type).$data;
        }
        if ($len <= 0xffffffff) {
            return pack('CNC', 0xc9, $len, $type).$data;
        }

        throw new PackException($ext, 'Extension data too big.');
    }

    private static function packFix($code, $num)
    {
        return chr($code | $num);
    }

    private static function packU8($code, $num)
    {
        return pack('CC', $code, $num);
    }

    private static function packU16($code, $num)
    {
        return pack('Cn', $code, $num);
    }

    private static function packU32($code, $num)
    {
        return pack('CN', $code, $num);
    }

    private static function packU64($code, $num)
    {
        $hi = ($num & 0xffffffff00000000) >> 32;
        $lo = $num & 0x00000000ffffffff;

        return pack('CNN', $code, $hi, $lo);
    }

    private static function packDouble($num)
    {
        return "\xcb".strrev(pack('d', $num));
    }

    private static function packInt($num)
    {
        if ($num >= 0) {
            if ($num <= 0x7f) {
                return self::packFix(0, $num);
            }
            if ($num <= 0xff) {
                return self::packU8(0xcc, $num);
            }
            if ($num <= 0xffff) {
                return self::packU16(0xcd, $num);
            }
            if ($num <= 0xffffffff) {
                return self::packU32(0xce, $num);
            }

            return self::packU64(0xcf, $num);
        }

        if ($num >= -0x20) {
            return self::packFix(0xe0, $num);
        }
        if ($num >= -0x80) {
            return self::packU8(0xd0, $num);
        }
        if ($num >= -0x8000) {
            return self::packU16(0xd1, $num);
        }
        if ($num >= -0x80000000) {
            return self::packU32(0xd2, $num);
        }

        return self::packU64(0xd3, $num);
    }

    private static function packStr($str)
    {
        $len = strlen($str);

        if ($len < 32) {
            return self::packFix(0xa0, $len).$str;
        }
        if ($len <= 0xff) {
            return self::packU8(0xd9, $len).$str;
        }
        if ($len <= 0xffff) {
            return self::packU16(0xda, $len).$str;
        }

        return self::packU32(0xdb, $len).$str;
    }

    private function packBin($str)
    {
        $len = strlen($str);

        if ($len <= 0xff) {
            return self::packU8(0xc4, $len).$str;
        }
        if ($len <= 0xffff) {
            return self::packU16(0xc5, $len).$str;
        }

        return self::packU32(0xc6, $len).$str;
    }
}
