# msgpack.php

A pure PHP implementation of the [MessagePack](https://msgpack.org/) serialization format.

[![Build Status](https://travis-ci.org/rybakit/msgpack.php.svg?branch=master)](https://travis-ci.org/rybakit/msgpack.php)
[![Code Coverage](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)


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
    * [Performance](#performance)
 * [License](#license)


## Installation

The recommended way to install the library is through [Composer](http://getcomposer.org):

```sh
$ composer require rybakit/msgpack
```

> *`rybakit/msgpack` requires PHP >= 7.1.1. For older PHP versions or HHVM please use 
> the [0.3.1](https://github.com/rybakit/msgpack.php/tree/v0.3.1) version of this library.*


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
$mpArr1 = $packer->pack([1, 2]);                   // MP array [1, 2]
$mpArr2 = $packer->pack([0 => 1, 1 => 2]);         // MP array [1, 2]
$mpMap1 = $packer->pack([0 => 1, 2 => 3]);         // MP map {0: 1, 2: 3}
$mpMap2 = $packer->pack([1 => 2, 2 => 3]);         // MP map {1: 2, 2: 3}
$mpMap3 = $packer->pack(['foo' => 1, 'bar' => 2]); // MP map {foo: 1, bar: 2}
```

However, sometimes you need to pack a sequential array as a MessagePack map.
To do this, use the `packMap` method:

```php
$mpMap = $packer->packMap([1, 2]); // {0: 1, 1: 2}
```

Here is a list of type-specific packing methods:

```php
$packer->packNil();          // MP nil
$packer->packBool(true);     // MP bool
$packer->packInt(42);        // MP int
$packer->packFloat(M_PI);    // MP float
$packer->packStr('foo');     // MP str
$packer->packBin("\x80");    // MP bin
$packer->packArray([1, 2]);  // MP array
$packer->packMap([1, 2]);    // MP map
$packer->packExt(1, "\xaa"); // MP ext
```

> *Check the ["Type transformers"](#type-transformers) section below on how to pack custom types.*


#### Packing options

The `Packer` object supports a number of bitmask-based options for fine-tuning the packing 
process (defaults are in bold):

| Name               | Description                                                   |
| ------------------ | ------------------------------------------------------------- |
| FORCE_STR          | Forces PHP strings to be packed as MessagePack UTF-8 strings  |
| FORCE_BIN          | Forces PHP strings to be packed as MessagePack binary data    |
| **DETECT_STR_BIN** | Detects MessagePack str/bin type automatically                |
|                    |                                                               |
| FORCE_ARR          | Forces PHP arrays to be packed as MessagePack arrays          |
| FORCE_MAP          | Forces PHP arrays to be packed as MessagePack maps            |
| **DETECT_ARR_MAP** | Detects MessagePack array/map type automatically              |
|                    |                                                               |
| FORCE_FLOAT32      | Forces PHP floats to be packed as 32-bits MessagePack floats  |
| **FORCE_FLOAT64**  | Forces PHP floats to be packed as 64-bits MessagePack floats  |

> *The type detection mode (`DETECT_STR_BIN`/`DETECT_ARR_MAP`) adds some overhead which can be noticed when you pack 
> large (16- and 32-bit) arrays or strings. However, if you know the value type in advance (for example, you only 
> work with UTF-8 strings or/and associative arrays), you can eliminate this overhead by forcing the packer to use 
> the appropriate type, which will save it from running the auto-detection routine. Another option is to explicitly 
> specify the value type. The library provides 2 auxiliary classes for this, `Map` and `Binary`.
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
$unpacker->unpackExt();   // PHP Ext class
```


#### Unpacking options

The `BufferUnpacker` object supports a number of bitmask-based options for fine-tuning the unpacking process (defaults 
are in bold):

| Name                | Description                                                |
| ------------------- | ---------------------------------------------------------- |
| BIGINT_AS_EXCEPTION | Throws an exception on integer overflow <sup>[1]</sup>     |
| BIGINT_AS_GMP       | Converts overflowed integers to GMP objects <sup>[2]</sup> |
| **BIGINT_AS_STR**   | Converts overflowed integers to strings                    |

> *1. The binary MessagePack format has unsigned 64-bit as its largest integer data type,
>    but PHP does not support such integers, which means that an overflow can occur during unpacking.*
>
> *2. Make sure that the [GMP](http://php.net/manual/en/book.gmp.php) extension is enabled.*


Examples:

```php
use MessagePack\BufferUnpacker;
use MessagePack\UnpackOptions;

$packedUint64 = "\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff";

$unpacker = new BufferUnpacker($packedUint64);
var_dump($unpacker->unpack()); // string(20) "18446744073709551615"

$unpacker = new BufferUnpacker($packedUint64, UnpackOptions::BIGINT_AS_GMP);
var_dump($unpacker->unpack()); // object(GMP) {...}

$unpacker = new BufferUnpacker($packedUint64, UnpackOptions::BIGINT_AS_EXCEPTION);
$unpacker->unpack(); // throws MessagePack\Exception\IntegerOverflowException
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
type you need to create and register a transformer. The transformer should either implement the `Packable` 
or the `Extension` interface.

The purpose of `Packable` transformers is to serialize a specific value to one of the basic MessagePack types. A good 
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

class MapTransformer implements Packable
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
$packer->registerTransformer(new MapTransformer());

$packed = $packer->pack([
    [1, 2, 3],          // MP array
    new Map([1, 2, 3]), // MP map
]);
```

Transformers implementing the `Extension` interface are intended for packing *and* unpacking application-specific types 
using the MessagePack's [Extension type](https://github.com/msgpack/msgpack/blob/master/spec.md#extension-types). 
For example, the code below shows how to create a transformer that allows you to work transparently with `DateTime` 
objects:

```php
use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\TypeTransformer\Extension;

class DateTimeTransformer implements Extension
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
            $packer->packStr($value->format(\DateTime::RFC3339))
        );
    }

    public function unpack(BufferUnpacker $unpacker, int $extLength)
    {
        return new \DateTime($unpacker->unpackStr());
    }
}
```

Register `DateTimeTransformer` for both the packer and the unpacker with a unique extension type (an integer from 0 
to 127) and you are ready to go:

```php
use App\MessagePack\DateTimeTransformer;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

$transformer = new DateTimeTransformer(42);

$packer = new Packer();
$packer->registerTransformer($transformer);

$unpacker = new BufferUnpacker();
$unpacker->registerTransformer($transformer);

$packed = $packer->pack(new DateTime());
$date = $unpacker->reset($packed)->unpack();
```

> *More type transformer examples can be found in the [examples](examples) directory.* 


## Exceptions

If an error occurs during packing/unpacking, a `PackingFailedException` or `UnpackingFailedException` will be thrown, 
respectively.

In addition, there are three more exceptions that can be thrown during unpacking:

 * `InsufficientDataException`
 * `IntegerOverflowException`
 * `InvalidCodeException`

An `InvalidOptionException` will be thrown in case an invalid option (or a combination of mutually exclusive options) 
is used.


## Tests

Run tests as follows:

```sh
$ phpunit
```

Also, if you already have Docker installed, you can run the tests in a docker container.
First, create a container:

```sh
$ ./dockerfile.sh | docker build -t msgpack -
```

The command above will create a container named `msgpack` with PHP 7.2 runtime.
You may change the default runtime by defining the `PHP_RUNTIME` environment variable:

```sh
$ PHP_RUNTIME='php:7.1-cli' ./dockerfile.sh | docker build -t msgpack -
```

> *See a list of various runtimes [here](.travis.yml#L8).*

Then run the unit tests:

```sh
$ docker run --rm --name msgpack -v $(pwd):/msgpack -w /msgpack msgpack
```


#### Performance

To check performance, run:

```sh
$ php -n tests/bench.php
```

This command will output something like:

```
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

=============================================
Test/Target            Packer  BufferUnpacker
---------------------------------------------
nil .................. 0.0040 ........ 0.0194
false ................ 0.0047 ........ 0.0192
true ................. 0.0047 ........ 0.0195
7-bit uint #1 ........ 0.0065 ........ 0.0135
7-bit uint #2 ........ 0.0064 ........ 0.0135
7-bit uint #3 ........ 0.0065 ........ 0.0136
5-bit sint #1 ........ 0.0072 ........ 0.0189
5-bit sint #2 ........ 0.0067 ........ 0.0194
5-bit sint #3 ........ 0.0067 ........ 0.0190
8-bit uint #1 ........ 0.0090 ........ 0.0264
8-bit uint #2 ........ 0.0093 ........ 0.0263
8-bit uint #3 ........ 0.0093 ........ 0.0264
16-bit uint #1 ....... 0.0121 ........ 0.0341
16-bit uint #2 ....... 0.0121 ........ 0.0339
16-bit uint #3 ....... 0.0122 ........ 0.0343
32-bit uint #1 ....... 0.0136 ........ 0.0437
32-bit uint #2 ....... 0.0136 ........ 0.0438
32-bit uint #3 ....... 0.0136 ........ 0.0436
64-bit uint #1 ....... 0.0222 ........ 0.0555
64-bit uint #2 ....... 0.0222 ........ 0.0553
8-bit int #1 ......... 0.0091 ........ 0.0306
8-bit int #2 ......... 0.0094 ........ 0.0309
8-bit int #3 ......... 0.0092 ........ 0.0301
16-bit int #1 ........ 0.0122 ........ 0.0350
16-bit int #2 ........ 0.0123 ........ 0.0348
16-bit int #3 ........ 0.0124 ........ 0.0348
32-bit int #1 ........ 0.0138 ........ 0.0467
32-bit int #2 ........ 0.0138 ........ 0.0459
32-bit int #3 ........ 0.0138 ........ 0.0458
64-bit int #1 ........ 0.0220 ........ 0.0542
64-bit int #2 ........ 0.0220 ........ 0.0540
64-bit int #3 ........ 0.0220 ........ 0.0538
64-bit float #1 ...... 0.0177 ........ 0.0458
64-bit float #2 ...... 0.0175 ........ 0.0460
64-bit float #3 ...... 0.0175 ........ 0.0451
fix string #1 ........ 0.0205 ........ 0.0170
fix string #2 ........ 0.0223 ........ 0.0274
fix string #3 ........ 0.0225 ........ 0.0287
fix string #4 ........ 0.0242 ........ 0.0286
8-bit string #1 ...... 0.0270 ........ 0.0389
8-bit string #2 ...... 0.0314 ........ 0.0390
8-bit string #3 ...... 0.0381 ........ 0.0390
16-bit string #1 ..... 0.0416 ........ 0.0474
16-bit string #2 ..... 3.3089 ........ 0.3195
32-bit string ........ 3.3042 ........ 0.3272
wide char string #1 .. 0.0228 ........ 0.0286
wide char string #2 .. 0.0289 ........ 0.0406
8-bit binary #1 ...... 0.0275 ........ 0.0374
8-bit binary #2 ...... 0.0244 ........ 0.0389
8-bit binary #3 ...... 0.0247 ........ 0.0393
16-bit binary ........ 0.0288 ........ 0.0489
32-bit binary ........ 0.3754 ........ 0.3320
fix array #1 ......... 0.0149 ........ 0.0183
fix array #2 ......... 0.0554 ........ 0.0769
16-bit array #1 ...... 0.1541 ........ 0.1940
16-bit array #2 ........... S ............. S
32-bit array .............. S ............. S
complex array ........ 0.2367 ........ 0.3396
fix map #1 ........... 0.1209 ........ 0.1499
fix map #2 ........... 0.0523 ........ 0.0625
fix map #3 ........... 0.0592 ........ 0.0885
fix map #4 ........... 0.0535 ........ 0.0793
16-bit map #1 ........ 0.3075 ........ 0.3574
16-bit map #2 ............. S ............. S
32-bit map ................ S ............. S
complex map .......... 0.3565 ........ 0.4414
fixext 1 ............. 0.0154 ........ 0.0455
fixext 2 ............. 0.0154 ........ 0.0489
fixext 4 ............. 0.0155 ........ 0.0502
fixext 8 ............. 0.0169 ........ 0.0497
fixext 16 ............ 0.0162 ........ 0.0497
8-bit ext ............ 0.0196 ........ 0.0587
16-bit ext ........... 0.0238 ........ 0.0666
32-bit ext ........... 0.3658 ........ 0.3455
=============================================
Total                  9.6996          5.2143
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
$ export MP_BENCH_TARGETS=pure_p
$ export MP_BENCH_ITERATIONS=1000000
$ export MP_BENCH_ROUNDS=5
$ # a comma separated list of test names
$ export MP_BENCH_TESTS='complex array, complex map'
$ # or a group name
$ # export MP_BENCH_TESTS='-@slow' // @pecl_comp
$ # or a regexp
$ # export MP_BENCH_TESTS='/complex (array|map)/'
$ php -n tests/bench.php
```

Another example, benchmarking both the library and the [msgpack pecl extension](https://pecl.php.net/package/msgpack):

```sh
$ MP_BENCH_TARGETS=pure_ps,pure_bu,pecl_p,pecl_u php -n -dextension=msgpack.so tests/bench.php
```

Output:

```
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

======================================================================================
Test/Target           Packer (force_str)  BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------------
nil ............................. 0.0040 ........ 0.0191 ...... 0.0065 ........ 0.0048
false ........................... 0.0047 ........ 0.0194 ...... 0.0066 ........ 0.0048
true ............................ 0.0047 ........ 0.0192 ...... 0.0064 ........ 0.0050
7-bit uint #1 ................... 0.0064 ........ 0.0134 ...... 0.0066 ........ 0.0049
7-bit uint #2 ................... 0.0065 ........ 0.0135 ...... 0.0066 ........ 0.0048
7-bit uint #3 ................... 0.0067 ........ 0.0135 ...... 0.0067 ........ 0.0048
5-bit sint #1 ................... 0.0066 ........ 0.0188 ...... 0.0081 ........ 0.0061
5-bit sint #2 ................... 0.0067 ........ 0.0188 ...... 0.0066 ........ 0.0049
5-bit sint #3 ................... 0.0066 ........ 0.0188 ...... 0.0066 ........ 0.0049
8-bit uint #1 ................... 0.0089 ........ 0.0264 ...... 0.0068 ........ 0.0053
8-bit uint #2 ................... 0.0089 ........ 0.0262 ...... 0.0066 ........ 0.0053
8-bit uint #3 ................... 0.0091 ........ 0.0269 ...... 0.0066 ........ 0.0053
16-bit uint #1 .................. 0.0121 ........ 0.0334 ...... 0.0072 ........ 0.0053
16-bit uint #2 .................. 0.0125 ........ 0.0338 ...... 0.0068 ........ 0.0053
16-bit uint #3 .................. 0.0121 ........ 0.0334 ...... 0.0068 ........ 0.0053
32-bit uint #1 .................. 0.0136 ........ 0.0505 ...... 0.0067 ........ 0.0053
32-bit uint #2 .................. 0.0141 ........ 0.0438 ...... 0.0067 ........ 0.0053
32-bit uint #3 .................. 0.0136 ........ 0.0439 ...... 0.0069 ........ 0.0053
64-bit uint #1 .................. 0.0222 ........ 0.0554 ...... 0.0066 ........ 0.0053
64-bit uint #2 .................. 0.0222 ........ 0.0552 ...... 0.0066 ........ 0.0052
8-bit int #1 .................... 0.0091 ........ 0.0299 ...... 0.0068 ........ 0.0052
8-bit int #2 .................... 0.0091 ........ 0.0296 ...... 0.0067 ........ 0.0053
8-bit int #3 .................... 0.0093 ........ 0.0301 ...... 0.0067 ........ 0.0052
16-bit int #1 ................... 0.0122 ........ 0.0344 ...... 0.0067 ........ 0.0053
16-bit int #2 ................... 0.0122 ........ 0.0344 ...... 0.0068 ........ 0.0053
16-bit int #3 ................... 0.0123 ........ 0.0343 ...... 0.0067 ........ 0.0054
32-bit int #1 ................... 0.0137 ........ 0.0461 ...... 0.0067 ........ 0.0053
32-bit int #2 ................... 0.0137 ........ 0.0456 ...... 0.0067 ........ 0.0053
32-bit int #3 ................... 0.0137 ........ 0.0456 ...... 0.0067 ........ 0.0052
64-bit int #1 ................... 0.0220 ........ 0.0538 ...... 0.0067 ........ 0.0052
64-bit int #2 ................... 0.0220 ........ 0.0537 ...... 0.0066 ........ 0.0053
64-bit int #3 ................... 0.0219 ........ 0.0532 ...... 0.0066 ........ 0.0054
64-bit float #1 ................. 0.0176 ........ 0.0454 ...... 0.0066 ........ 0.0052
64-bit float #2 ................. 0.0175 ........ 0.0456 ...... 0.0066 ........ 0.0052
64-bit float #3 ................. 0.0176 ........ 0.0447 ...... 0.0066 ........ 0.0054
fix string #1 ................... 0.0096 ........ 0.0174 ...... 0.0069 ........ 0.0052
fix string #2 ................... 0.0112 ........ 0.0270 ...... 0.0068 ........ 0.0068
fix string #3 ................... 0.0114 ........ 0.0285 ...... 0.0067 ........ 0.0068
fix string #4 ................... 0.0111 ........ 0.0283 ...... 0.0067 ........ 0.0066
8-bit string #1 ................. 0.0137 ........ 0.0380 ...... 0.0069 ........ 0.0065
8-bit string #2 ................. 0.0139 ........ 0.0383 ...... 0.0069 ........ 0.0067
8-bit string #3 ................. 0.0140 ........ 0.0381 ...... 0.0109 ........ 0.0068
16-bit string #1 ................ 0.0180 ........ 0.0469 ...... 0.0110 ........ 0.0072
16-bit string #2 ................ 0.3589 ........ 0.3170 ...... 0.3459 ........ 0.2751
32-bit string ................... 0.3662 ........ 0.3307 ...... 0.3458 ........ 0.2725
wide char string #1 ............. 0.0113 ........ 0.0291 ...... 0.0067 ........ 0.0067
wide char string #2 ............. 0.0137 ........ 0.0384 ...... 0.0068 ........ 0.0065
8-bit binary #1 ...................... I ............. I ........... F ............. I
8-bit binary #2 ...................... I ............. I ........... F ............. I
8-bit binary #3 ...................... I ............. I ........... F ............. I
16-bit binary ........................ I ............. I ........... F ............. I
32-bit binary ........................ I ............. I ........... F ............. I
fix array #1 .................... 0.0145 ........ 0.0176 ...... 0.0151 ........ 0.0066
fix array #2 .................... 0.0453 ........ 0.0763 ...... 0.0190 ........ 0.0195
16-bit array #1 ................. 0.1579 ........ 0.1940 ...... 0.0324 ........ 0.0437
16-bit array #2 ...................... S ............. S ........... S ............. S
32-bit array ......................... S ............. S ........... S ............. S
complex array ........................ I ............. I ........... F ............. F
fix map #1 ........................... I ............. I ........... F ............. I
fix map #2 ...................... 0.0421 ........ 0.0626 ...... 0.0170 ........ 0.0168
fix map #3 ........................... I ............. I ........... F ............. I
fix map #4 ........................... I ............. I ........... F ............. I
16-bit map #1 ................... 0.3070 ........ 0.3493 ...... 0.0327 ........ 0.0663
16-bit map #2 ........................ S ............. S ........... S ............. S
32-bit map ........................... S ............. S ........... S ............. S
complex map ..................... 0.3127 ........ 0.4386 ...... 0.0664 ........ 0.0685
fixext 1 ............................. I ............. I ........... F ............. F
fixext 2 ............................. I ............. I ........... F ............. F
fixext 4 ............................. I ............. I ........... F ............. F
fixext 8 ............................. I ............. I ........... F ............. F
fixext 16 ............................ I ............. I ........... F ............. F
8-bit ext ............................ I ............. I ........... F ............. F
16-bit ext ........................... I ............. I ........... F ............. F
32-bit ext ........................... I ............. I ........... F ............. F
======================================================================================
Total                             2.1588          3.3263        1.1855          1.0172
Skipped                                4               4             4               4
Failed                                 0               0            17               9
Ignored                               17              17             0               8
```

> *Note, that this is not a fair comparison as the msgpack extension (0.5.2+, 2.0) doesn't
support **ext**, **bin** and UTF-8 **str** types.*


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
