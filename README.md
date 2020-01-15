# msgpack.php

[![Build Status](https://travis-ci.org/rybakit/msgpack.php.svg?branch=master)](https://travis-ci.org/rybakit/msgpack.php)
[![Code Coverage](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)
[![Mentioned in Awesome PHP](https://awesome.re/mentioned-badge.svg)](https://github.com/ziadoz/awesome-php#data-structure-and-storage)

A pure PHP implementation of the [MessagePack](https://msgpack.org/) serialization format.


## Features

 * Fully compliant with the latest [MessagePack specification](https://github.com/msgpack/msgpack/blob/master/spec.md),
   including **bin**, **str** and **ext** types
 * Supports [streaming unpacking](#unpacking)
 * Supports [unsigned 64-bit integers handling](#unpacking-options)
 * Supports [object serialization](#type-transformers)
 * [Fully tested](https://travis-ci.org/rybakit/msgpack.php)
 * [Relatively fast](#performance)


## Table of contents

 * [Installation](#installation)
 * [Usage](#usage)
   * [Packing](#packing)
     * [Packing options](#packing-options)
   * [Unpacking](#unpacking)
     * [Unpacking options](#unpacking-options)
 * [Extensions](#extensions)
 * [Type transformers](#type-transformers)
 * [Exceptions](#exceptions)
 * [Tests](#tests)
    * [Fuzzing](#fuzzing)
    * [Performance](#performance)
 * [License](#license)


## Installation

The recommended way to install the library is through [Composer](http://getcomposer.org):

```sh
composer require rybakit/msgpack
```


## Usage

### Packing

To pack values you can either use an instance of a `Packer`:

```php
use MessagePack\Packer;

$packer = new Packer();

...

$packed = $packer->pack($value);
```

or call a static method on the `MessagePack` class:

```php
use MessagePack\MessagePack;

...

$packed = MessagePack::pack($value);
```

In the examples above, the method `pack` automatically packs a value depending on its type. But not all PHP types 
can be uniquely translated to MessagePack types. For example, the MessagePack format defines `map` and `array` types, 
which are represented by a single `array` type in PHP. By default, the packer will pack a PHP array as a MessagePack 
array if it has sequential numeric keys, starting from `0` and as a MessagePack map otherwise:

```php
$mpArr1 = $packer->pack([1, 2]);               // MP array [1, 2]
$mpArr2 = $packer->pack([0 => 1, 1 => 2]);     // MP array [1, 2]
$mpMap1 = $packer->pack([0 => 1, 2 => 3]);     // MP map {0: 1, 2: 3}
$mpMap2 = $packer->pack([1 => 2, 2 => 3]);     // MP map {1: 2, 2: 3}
$mpMap3 = $packer->pack(['a' => 1, 'b' => 2]); // MP map {a: 1, b: 2}
```

However, sometimes you need to pack a sequential array as a MessagePack map.
To do this, use the `packMap` method:

```php
$mpMap = $packer->packMap([1, 2]); // {0: 1, 1: 2}
```

Here is a list of type-specific packing methods:

```php
$packer->packNil();           // MP nil
$packer->packBool(true);      // MP bool
$packer->packInt(42);         // MP int
$packer->packFloat(M_PI);     // MP float
$packer->packStr('foo');      // MP str
$packer->packBin("\x80");     // MP bin
$packer->packArray([1, 2]);   // MP array
$packer->packMap(['a' => 1]); // MP map
$packer->packExt(1, "\xaa");  // MP ext
```

> *Check the ["Type transformers"](#type-transformers) section below on how to pack custom types.*


#### Packing options

The `Packer` object supports a number of bitmask-based options for fine-tuning the packing 
process (defaults are in bold):

| Name                 | Description                                                   |
| -------------------- | ------------------------------------------------------------- |
| `FORCE_STR`          | Forces PHP strings to be packed as MessagePack UTF-8 strings  |
| `FORCE_BIN`          | Forces PHP strings to be packed as MessagePack binary data    |
| **`DETECT_STR_BIN`** | Detects MessagePack str/bin type automatically                |
|                      |                                                               |
| `FORCE_ARR`          | Forces PHP arrays to be packed as MessagePack arrays          |
| `FORCE_MAP`          | Forces PHP arrays to be packed as MessagePack maps            |
| **`DETECT_ARR_MAP`** | Detects MessagePack array/map type automatically              |
|                      |                                                               |
| `FORCE_FLOAT32`      | Forces PHP floats to be packed as 32-bits MessagePack floats  |
| **`FORCE_FLOAT64`**  | Forces PHP floats to be packed as 64-bits MessagePack floats  |

> *The type detection mode (`DETECT_STR_BIN`/`DETECT_ARR_MAP`) adds some overhead which can be noticed when you pack 
> large (16- and 32-bit) arrays or strings. However, if you know the value type in advance (for example, you only 
> work with UTF-8 strings or/and associative arrays), you can eliminate this overhead by forcing the packer to use 
> the appropriate type, which will save it from running the auto-detection routine. Another option is to explicitly 
> specify the value type. The library provides 2 auxiliary classes for this, `Map` and `Bin`.
> Check the ["Type transformers"](#type-transformers) section below for details.*

Examples:

```php
use MessagePack\Packer;
use MessagePack\PackOptions;

// pack PHP strings to MP strings, PHP arrays to MP maps 
// and PHP 64-bit floats (doubles) to MP 32-bit floats
$packer = new Packer(PackOptions::FORCE_STR | PackOptions::FORCE_MAP | PackOptions::FORCE_FLOAT32);

// pack PHP strings to MP binaries and PHP arrays to MP arrays
$packer = new Packer(PackOptions::FORCE_BIN | PackOptions::FORCE_ARR);

// these will throw MessagePack\Exception\InvalidOptionException
$packer = new Packer(PackOptions::FORCE_STR | PackOptions::FORCE_BIN);
$packer = new Packer(PackOptions::FORCE_FLOAT32 | PackOptions::FORCE_FLOAT64);
```


### Unpacking

To unpack data you can either use an instance of a `BufferUnpacker`:

```php
use MessagePack\BufferUnpacker;

$unpacker = new BufferUnpacker();

...

$unpacker->reset($packed);
$value = $unpacker->unpack();
```

or call a static method on the `MessagePack` class:

```php
use MessagePack\MessagePack;

...

$value = MessagePack::unpack($packed);
```

If the packed data is received in chunks (e.g. when reading from a stream), use the `tryUnpack` method, which attempts 
to unpack data and returns an array of unpacked messages (if any) instead of throwing an `InsufficientDataException`:

```php
while ($chunk = ...) {
    $unpacker->append($chunk);
    if ($messages = $unpacker->tryUnpack()) {
        return $messages;
    }
}
```

If you want to unpack from a specific position in a buffer, use `seek`:

```php
$unpacker->seek(42); // set position equal to 42 bytes
$unpacker->seek(-8); // set position to 8 bytes before the end of the buffer
```

To skip bytes from the current position, use `skip`:

```php
$unpacker->skip(10); // set position to 10 bytes ahead of the current position
```

To get the number of remaining (unread) bytes in the buffer:

```php
$unreadBytesCount = $unpacker->getRemainingCount();
```

To check whether the buffer has unread data:

```php
$hasUnreadBytes = $unpacker->hasRemaining();
```

If needed, you can remove already read data from the buffer by calling:

```php
$releasedBytesCount = $unpacker->release();
```

With the `read` method you can read raw (packed) data:

```php
$packedData = $unpacker->read(2); // read 2 bytes
```

Besides the above methods `BufferUnpacker` provides type-specific unpacking methods, namely:

```php
$unpacker->unpackNil();   // PHP null
$unpacker->unpackBool();  // PHP bool
$unpacker->unpackInt();   // PHP int
$unpacker->unpackFloat(); // PHP float
$unpacker->unpackStr();   // PHP UTF-8 string
$unpacker->unpackBin();   // PHP binary string
$unpacker->unpackArray(); // PHP sequential array
$unpacker->unpackMap();   // PHP associative array
$unpacker->unpackExt();   // PHP MessagePack\Ext class
```


#### Unpacking options

The `BufferUnpacker` object supports a number of bitmask-based options for fine-tuning the unpacking process (defaults 
are in bold):

| Name                | Description                                                              |
| ------------------- | ------------------------------------------------------------------------ |
| **`BIGINT_AS_STR`** | Converts overflowed integers to strings <sup>[1]</sup>                   |
| `BIGINT_AS_GMP`     | Converts overflowed integers to `GMP` objects <sup>[2]</sup>             |
| `BIGINT_AS_DEC`     | Converts overflowed integers to `Decimal\Decimal` objects <sup>[3]</sup> |

> *1. The binary MessagePack format has unsigned 64-bit as its largest integer data type,
>    but PHP does not support such integers, which means that an overflow can occur during unpacking.*
>
> *2. Make sure the [GMP](http://php.net/manual/en/book.gmp.php) extension is enabled.*
>
> *3. Make sure the [Decimal](http://php-decimal.io/) extension is enabled.*


Examples:

```php
use MessagePack\BufferUnpacker;
use MessagePack\UnpackOptions;

$packedUint64 = "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff";

$unpacker = new BufferUnpacker($packedUint64);
var_dump($unpacker->unpack()); // string(20) "18446744073709551615"

$unpacker = new BufferUnpacker($packedUint64, UnpackOptions::BIGINT_AS_GMP);
var_dump($unpacker->unpack()); // object(GMP) {...}

$unpacker = new BufferUnpacker($packedUint64, UnpackOptions::BIGINT_AS_DEC);
var_dump($unpacker->unpack()); // object(Decimal\Decimal) {...}
```


### Extensions

To define application-specific types use the `Ext` class:

```php
use MessagePack\Ext;
use MessagePack\MessagePack;

$packed = MessagePack::pack(new Ext(42, "\xaa"));
$ext = MessagePack::unpack($packed);

var_dump($ext->type === 42); // bool(true)
var_dump($ext->data === "\xaa"); // bool(true)
```


### Type transformers

In addition to [the basic types](https://github.com/msgpack/msgpack/blob/master/spec.md#type-system),
the library provides functionality to serialize and deserialize arbitrary types. In order to support a custom 
type you need to create and register a transformer. The transformer should implement either the `CanPack` interface 
or the `Extension` interface.

The purpose of `CanPack` transformers is to serialize a specific value to one of the basic MessagePack types. A good 
example of such a transformer is a `MapTransformer` that comes with the library. It serializes `Map` objects (which 
are simple wrappers around PHP arrays) to MessagePack maps. This is useful when you want to explicitly mark that 
a given PHP array must be packed as a MessagePack map, without triggering the type's auto-detection routine.

> *More types and type transformers can be found in [src/Type](src/Type) 
> and [src/TypeTransformer](src/TypeTransformer) directories.*

The implementation is trivial:

```php
namespace MessagePack\TypeTransformer;

use MessagePack\Packer;
use MessagePack\Type\Map;

class MapTransformer implements CanPack
{
    public function pack(Packer $packer, $value) : ?string
    {
        return $value instanceof Map
            ? $packer->packMap($value->map)
            : null;
    }
}
```

Once `MapTransformer` is registered, you can pack `Map` objects:

```php
use MessagePack\Packer;
use MessagePack\PackOptions;
use MessagePack\Type\Map;
use MessagePack\TypeTransformer\MapTransformer;

$packer = new Packer(PackOptions::FORCE_ARR);
$packer = $packer->extendWith(new MapTransformer());

$packed = $packer->pack([
    [1, 2, 3],          // MP array
    new Map([1, 2, 3]), // MP map
]);
```

Transformers implementing the `Extension` interface are intended to handle [extension types](https://github.com/msgpack/msgpack/blob/master/spec.md#extension-types). 
For example, the code below shows how to create an extension that allows you to work transparently with `DateTime` objects:

```php
use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\TypeTransformer\Extension;

class DateTimeExtension implements Extension 
{
    private $type;

    public function __construct(int $type)
    {
        $this->type = $type;
    }

    public function getType() : int
    {
        return $this->type;
    }

    public function pack(Packer $packer, $value) : ?string
    {
        if (!$value instanceof \DateTimeInterface) {
            return null;
        }

        return $packer->packExt($this->type,
            $packer->packStr($value->format('Y-m-d\TH:i:s.uP'))
        );
    }

    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        return new \DateTimeImmutable($unpacker->unpackStr());
    }
}
```

Register `DateTimeExtension` for both the packer and the unpacker with a unique extension type 
(an integer from 0 to 127) and you are ready to go:

```php
use App\MessagePack\DateTimeExtension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

$dateTimeExtension = new DateTimeExtension(42);

$packer = new Packer();
$packer = $packer->extendWith($dateTimeExtension);

$unpacker = new BufferUnpacker();
$unpacker = $unpacker->extendWith($dateTimeExtension);

$packed = $packer->pack(new DateTimeImmutable());
$date = $unpacker->reset($packed)->unpack();
```

> *More type transformer examples can be found in the [examples](examples) directory.* 


## Exceptions

If an error occurs during packing/unpacking, a `PackingFailedException` or an `UnpackingFailedException` will be thrown, 
respectively. In addition, an `InsufficientDataException` can be thrown during unpacking.

An `InvalidOptionException` will be thrown in case an invalid option (or a combination of mutually exclusive options) 
is used.


## Tests

Run tests as follows:

```sh
vendor/bin/phpunit
```

Also, if you already have Docker installed, you can run the tests in a docker container.
First, create a container:

```sh
./dockerfile.sh | docker build -t msgpack -
```

The command above will create a container named `msgpack` with PHP 7.4 runtime.
You may change the default runtime by defining the `PHP_RUNTIME` environment variable:

```sh
PHP_RUNTIME='php:7.3-cli' ./dockerfile.sh | docker build -t msgpack -
```

> *See a list of various runtimes [here](.travis.yml#L8).*

Then run the unit tests:

```sh
docker run --rm -v $PWD:/msgpack -w /msgpack msgpack
```


#### Fuzzing

To ensure that the unpacking works correctly with malformed/semi-malformed data,
you can use a testing technique called [Fuzzing](https://en.wikipedia.org/wiki/Fuzzing).
The library ships with a help file (target) for [PHP-Fuzzer](https://github.com/nikic/PHP-Fuzzer)
and can be used as follows:

```sh
php-fuzzer fuzz tests/fuzz_buffer_unpacker.php
```


#### Performance

To check performance, run:

```sh
php -n -dpcre.jit=1 -dzend_extension=opcache.so -dopcache.enable_cli=1 tests/bench.php
```

This command will output something like:

```
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

=============================================
Test/Target            Packer  BufferUnpacker
---------------------------------------------
nil .................. 0.0011 ........ 0.0105
false ................ 0.0007 ........ 0.0110
true ................. 0.0002 ........ 0.0105
7-bit uint #1 ........ 0.0025 ........ 0.0094
7-bit uint #2 ........ 0.0032 ........ 0.0093
7-bit uint #3 ........ 0.0033 ........ 0.0094
5-bit sint #1 ........ 0.0006 ........ 0.0095
5-bit sint #2 ........ 0.0038 ........ 0.0096
5-bit sint #3 ........ 0.0001 ........ 0.0085
8-bit uint #1 ........ 0.0045 ........ 0.0171
8-bit uint #2 ........ 0.0059 ........ 0.0165
8-bit uint #3 ........ 0.0049 ........ 0.0171
16-bit uint #1 ....... 0.0079 ........ 0.0212
16-bit uint #2 ....... 0.0095 ........ 0.0213
16-bit uint #3 ....... 0.0088 ........ 0.0216
32-bit uint #1 ....... 0.0092 ........ 0.0289
32-bit uint #2 ....... 0.0095 ........ 0.0288
32-bit uint #3 ....... 0.0103 ........ 0.0287
64-bit uint #1 ....... 0.0090 ........ 0.0325
64-bit uint #2 ....... 0.0107 ........ 0.0281
64-bit uint #3 ....... 0.0109 ........ 0.0284
8-bit int #1 ......... 0.0049 ........ 0.0232
8-bit int #2 ......... 0.0058 ........ 0.0190
8-bit int #3 ......... 0.0059 ........ 0.0202
16-bit int #1 ........ 0.0087 ........ 0.0256
16-bit int #2 ........ 0.0082 ........ 0.0227
16-bit int #3 ........ 0.0087 ........ 0.0230
32-bit int #1 ........ 0.0108 ........ 0.0289
32-bit int #2 ........ 0.0109 ........ 0.0293
32-bit int #3 ........ 0.0107 ........ 0.0295
64-bit int #1 ........ 0.0111 ........ 0.0287
64-bit int #2 ........ 0.0118 ........ 0.0288
64-bit int #3 ........ 0.0114 ........ 0.0278
64-bit int #4 ........ 0.0110 ........ 0.0288
64-bit float #1 ...... 0.0098 ........ 0.0284
64-bit float #2 ...... 0.0105 ........ 0.0283
64-bit float #3 ...... 0.0110 ........ 0.0284
fix string #1 ........ 0.0015 ........ 0.0097
fix string #2 ........ 0.0160 ........ 0.0179
fix string #3 ........ 0.0154 ........ 0.0201
fix string #4 ........ 0.0172 ........ 0.0204
8-bit string #1 ...... 0.0189 ........ 0.0268
8-bit string #2 ...... 0.0244 ........ 0.0259
8-bit string #3 ...... 0.0308 ........ 0.0267
16-bit string #1 ..... 0.0345 ........ 0.0327
16-bit string #2 ..... 3.2992 ........ 0.3161
32-bit string ........ 3.3559 ........ 0.3119
wide char string #1 .. 0.0189 ........ 0.0208
wide char string #2 .. 0.0225 ........ 0.0262
8-bit binary #1 ...... 0.0168 ........ 0.0252
8-bit binary #2 ...... 0.0172 ........ 0.0266
8-bit binary #3 ...... 0.0175 ........ 0.0269
16-bit binary ........ 0.0199 ........ 0.0326
32-bit binary ........ 0.3784 ........ 0.3144
fix array #1 ......... 0.0018 ........ 0.0098
fix array #2 ......... 0.0241 ........ 0.0320
fix array #3 ......... 0.0452 ........ 0.0464
16-bit array #1 ...... 0.1396 ........ 0.1532
16-bit array #2 ........... S ............. S
32-bit array .............. S ............. S
complex array ........ 0.1965 ........ 0.2357
fix map #1 ........... 0.0883 ........ 0.1058
fix map #2 ........... 0.0368 ........ 0.0382
fix map #3 ........... 0.0445 ........ 0.0552
fix map #4 ........... 0.0390 ........ 0.0473
16-bit map #1 ........ 0.2330 ........ 0.2916
16-bit map #2 ............. S ............. S
32-bit map ................ S ............. S
complex map .......... 0.2634 ........ 0.2905
fixext 1 ............. 0.0104 ........ 0.0355
fixext 2 ............. 0.0113 ........ 0.0380
fixext 4 ............. 0.0110 ........ 0.0376
fixext 8 ............. 0.0115 ........ 0.0379
fixext 16 ............ 0.0113 ........ 0.0373
8-bit ext ............ 0.0149 ........ 0.0478
16-bit ext ........... 0.0181 ........ 0.0455
32-bit ext ........... 0.3701 ........ 0.3304
=============================================
Total                  9.1440          3.9944
Skipped                     4               4
Failed                      0               0
Ignored                     0               0
```

You may change default benchmark settings by defining the following environment variables:

 * `MP_BENCH_TARGETS` (pure_p, pure_ps, pure_pa, pure_psa, pure_bu, pecl_p, pecl_u)
 * `MP_BENCH_ITERATIONS`/`MP_BENCH_DURATION`
 * `MP_BENCH_ROUNDS`
 * `MP_BENCH_TESTS`

For example:

```sh
export MP_BENCH_TARGETS=pure_p
export MP_BENCH_ITERATIONS=1000000
export MP_BENCH_ROUNDS=5
# a comma separated list of test names
export MP_BENCH_TESTS='complex array, complex map'
# or a group name
# export MP_BENCH_TESTS='-@slow' // @pecl_comp
# or a regexp
# export MP_BENCH_TESTS='/complex (array|map)/'
php -n -dpcre.jit=1 -dzend_extension=opcache.so -dopcache.enable_cli=1 tests/bench.php
```

Another example, benchmarking both the library and the [msgpack pecl extension](https://pecl.php.net/package/msgpack):

```sh
MP_BENCH_TARGETS=pure_ps,pure_bu,pecl_p,pecl_u \
php -n -dpcre.jit=1 -dextension=msgpack.so -dzend_extension=opcache.so -dopcache.enable_cli=1 tests/bench.php
```

Output:

```
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

======================================================================================
Test/Target           Packer (force_str)  BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------------
nil ............................. 0.0017 ........ 0.0106 ...... 0.0022 ........ 0.0028
false ........................... 0.0007 ........ 0.0111 ...... 0.0049 ........ 0.0027
true ............................ 0.0007 ........ 0.0111 ...... 0.0035 ........ 0.0035
7-bit uint #1 ................... 0.0047 ........ 0.0103 ...... 0.0045 ........ 0.0030
7-bit uint #2 ................... 0.0030 ........ 0.0086 ...... 0.0022 ........ 0.0040
7-bit uint #3 ................... 0.0032 ........ 0.0084 ...... 0.0038 ........ 0.0059
5-bit sint #1 ................... 0.0042 ........ 0.0111 ...... 0.0024 ........ 0.0038
5-bit sint #2 ................... 0.0048 ........ 0.0103 ...... 0.0060 ........ 0.0053
5-bit sint #3 ................... 0.0057 ........ 0.0108 ...... 0.0016 ........ 0.0041
8-bit uint #1 ................... 0.0062 ........ 0.0155 ...... 0.0032 ........ 0.0062
8-bit uint #2 ................... 0.0062 ........ 0.0162 ...... 0.0011 ........ 0.0031
8-bit uint #3 ................... 0.0059 ........ 0.0174 ...... 0.0035 ........ 0.0032
16-bit uint #1 .................. 0.0097 ........ 0.0220 ...... 0.0042 ........ 0.0046
16-bit uint #2 .................. 0.0092 ........ 0.0222 ...... 0.0031 ........ 0.0027
16-bit uint #3 .................. 0.0072 ........ 0.0211 ...... 0.0025 ........ 0.0036
32-bit uint #1 .................. 0.0109 ........ 0.0274 ...... 0.0054 ........ 0.0018
32-bit uint #2 .................. 0.0124 ........ 0.0279 ...... 0.0018 ........ 0.0027
32-bit uint #3 .................. 0.0088 ........ 0.0282 ...... 0.0049 ........ 0.0022
64-bit uint #1 .................. 0.0113 ........ 0.0295 ...... 0.0047 ........ 0.0032
64-bit uint #2 .................. 0.0090 ........ 0.0285 ...... 0.0043 ........ 0.0026
64-bit uint #3 .................. 0.0115 ........ 0.0289 ...... 0.0047 ........ 0.0050
8-bit int #1 .................... 0.0065 ........ 0.0193 ...... 0.0051 ........ 0.0040
8-bit int #2 .................... 0.0068 ........ 0.0199 ...... 0.0035 ........ 0.0047
8-bit int #3 .................... 0.0065 ........ 0.0206 ...... 0.0047 ........ 0.0048
16-bit int #1 ................... 0.0089 ........ 0.0229 ...... 0.0039 ........ 0.0050
16-bit int #2 ................... 0.0082 ........ 0.0236 ...... 0.0042 ........ 0.0049
16-bit int #3 ................... 0.0088 ........ 0.0224 ...... 0.0034 ........ 0.0050
32-bit int #1 ................... 0.0099 ........ 0.0298 ...... 0.0048 ........ 0.0072
32-bit int #2 ................... 0.0115 ........ 0.0274 ...... 0.0047 ........ 0.0038
32-bit int #3 ................... 0.0088 ........ 0.0298 ...... 0.0036 ........ 0.0044
64-bit int #1 ................... 0.0109 ........ 0.0292 ...... 0.0029 ........ 0.0026
64-bit int #2 ................... 0.0113 ........ 0.0292 ...... 0.0049 ........ 0.0033
64-bit int #3 ................... 0.0102 ........ 0.0284 ...... 0.0038 ........ 0.0037
64-bit int #4 ................... 0.0116 ........ 0.0274 ...... 0.0033 ........ 0.0050
64-bit float #1 ................. 0.0100 ........ 0.0278 ...... 0.0022 ........ 0.0038
64-bit float #2 ................. 0.0117 ........ 0.0286 ...... 0.0034 ........ 0.0048
64-bit float #3 ................. 0.0096 ........ 0.0287 ...... 0.0036 ........ 0.0032
fix string #1 ................... 0.0017 ........ 0.0109 ...... 0.0050 ........ 0.0055
fix string #2 ................... 0.0079 ........ 0.0183 ...... 0.0039 ........ 0.0057
fix string #3 ................... 0.0080 ........ 0.0203 ...... 0.0025 ........ 0.0065
fix string #4 ................... 0.0064 ........ 0.0209 ...... 0.0032 ........ 0.0047
8-bit string #1 ................. 0.0098 ........ 0.0258 ...... 0.0026 ........ 0.0040
8-bit string #2 ................. 0.0096 ........ 0.0264 ...... 0.0038 ........ 0.0060
8-bit string #3 ................. 0.0084 ........ 0.0283 ...... 0.0064 ........ 0.0047
16-bit string #1 ................ 0.0128 ........ 0.0329 ...... 0.0086 ........ 0.0059
16-bit string #2 ................ 0.3606 ........ 0.3058 ...... 0.3627 ........ 0.2826
32-bit string ................... 0.3622 ........ 0.3092 ...... 0.3531 ........ 0.2811
wide char string #1 ............. 0.0081 ........ 0.0210 ...... 0.0038 ........ 0.0070
wide char string #2 ............. 0.0088 ........ 0.0263 ...... 0.0037 ........ 0.0051
8-bit binary #1 ...................... I ............. I ........... F ............. I
8-bit binary #2 ...................... I ............. I ........... F ............. I
8-bit binary #3 ...................... I ............. I ........... F ............. I
16-bit binary ........................ I ............. I ........... F ............. I
32-bit binary ........................ I ............. I ........... F ............. I
fix array #1 .................... 0.0027 ........ 0.0103 ...... 0.0119 ........ 0.0066
fix array #2 .................... 0.0249 ........ 0.0325 ...... 0.0146 ........ 0.0159
fix array #3 .................... 0.0370 ........ 0.0473 ...... 0.0176 ........ 0.0185
16-bit array #1 ................. 0.1363 ........ 0.1589 ...... 0.0301 ........ 0.0426
16-bit array #2 ...................... S ............. S ........... S ............. S
32-bit array ......................... S ............. S ........... S ............. S
complex array ........................ I ............. I ........... F ............. F
fix map #1 ........................... I ............. I ........... F ............. I
fix map #2 ...................... 0.0283 ........ 0.0389 ...... 0.0152 ........ 0.0197
fix map #3 ........................... I ............. I ........... F ............. I
fix map #4 ........................... I ............. I ........... F ............. I
16-bit map #1 ................... 0.2530 ........ 0.2938 ...... 0.0299 ........ 0.0646
16-bit map #2 ........................ S ............. S ........... S ............. S
32-bit map ........................... S ............. S ........... S ............. S
complex map ..................... 0.2475 ........ 0.2905 ...... 0.0659 ........ 0.0670
fixext 1 ............................. I ............. I ........... F ............. F
fixext 2 ............................. I ............. I ........... F ............. F
fixext 4 ............................. I ............. I ........... F ............. F
fixext 8 ............................. I ............. I ........... F ............. F
fixext 16 ............................ I ............. I ........... F ............. F
8-bit ext ............................ I ............. I ........... F ............. F
16-bit ext ........................... I ............. I ........... F ............. F
32-bit ext ........................... I ............. I ........... F ............. F
======================================================================================
Total                             1.8217          2.5103        1.0807          0.9997
Skipped                                4               4             4               4
Failed                                 0               0            17               9
Ignored                               17              17             0               8
```

> *Note that the msgpack extension (0.5.2+, 2.0) doesn't support **ext**, **bin** and UTF-8 **str** types.*


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
