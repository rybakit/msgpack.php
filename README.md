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
 * Works with PHP 5.4-7.x and HHVM 3.9+
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

> *Check the ["Type transformers"](#type-transformers) section below on how to pack arbitrary PHP objects.*


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

// cast PHP strings to MP strings, PHP arrays to MP maps 
// and PHP 64-bit floats (doubles) to MP 32-bit floats
$packer = new Packer(PackOptions::FORCE_STR | PackOptions::FORCE_MAP | PackOptions::FORCE_FLOAT32);

// cast PHP strings to MP binaries and PHP arrays to MP arrays
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
    public function pack(Packer $packer, $value)
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

    public function __construct($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function pack(Packer $packer, $value)
    {
        if (!$value instanceof \DateTimeInterface && !$value instanceof \DateTime) {
            return null;
        }

        return $packer->packExt($this->type,
            $packer->packStr($value->format(\DateTime::RFC3339))
        );
    }

    public function unpack(BufferUnpacker $unpacker, $extLength)
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
nil .................. 0.0059 ........ 0.0200
false ................ 0.0070 ........ 0.0199
true ................. 0.0071 ........ 0.0199
7-bit uint #1 ........ 0.0066 ........ 0.0137
7-bit uint #2 ........ 0.0065 ........ 0.0140
7-bit uint #3 ........ 0.0066 ........ 0.0142
5-bit sint #1 ........ 0.0066 ........ 0.0191
5-bit sint #2 ........ 0.0071 ........ 0.0192
5-bit sint #3 ........ 0.0070 ........ 0.0196
8-bit uint #1 ........ 0.0091 ........ 0.0267
8-bit uint #2 ........ 0.0091 ........ 0.0276
8-bit uint #3 ........ 0.0092 ........ 0.0269
16-bit uint #1 ....... 0.0124 ........ 0.0344
16-bit uint #2 ....... 0.0123 ........ 0.0350
16-bit uint #3 ....... 0.0129 ........ 0.0349
32-bit uint #1 ....... 0.0139 ........ 0.0452
32-bit uint #2 ....... 0.0141 ........ 0.0444
32-bit uint #3 ....... 0.0139 ........ 0.0446
64-bit uint #1 ....... 0.0227 ........ 0.0567
64-bit uint #2 ....... 0.0235 ........ 0.0570
8-bit int #1 ......... 0.0094 ........ 0.0305
8-bit int #2 ......... 0.0092 ........ 0.0307
8-bit int #3 ......... 0.0097 ........ 0.0305
16-bit int #1 ........ 0.0125 ........ 0.0355
16-bit int #2 ........ 0.0124 ........ 0.0356
16-bit int #3 ........ 0.0128 ........ 0.0350
32-bit int #1 ........ 0.0139 ........ 0.0470
32-bit int #2 ........ 0.0144 ........ 0.0467
32-bit int #3 ........ 0.0139 ........ 0.0476
64-bit int #1 ........ 0.0226 ........ 0.0558
64-bit int #2 ........ 0.0226 ........ 0.0551
64-bit int #3 ........ 0.0223 ........ 0.0540
64-bit float #1 ...... 0.0180 ........ 0.0469
64-bit float #2 ...... 0.0182 ........ 0.0468
64-bit float #3 ...... 0.0190 ........ 0.0466
fix string #1 ........ 0.0213 ........ 0.0180
fix string #2 ........ 0.0243 ........ 0.0283
fix string #3 ........ 0.0230 ........ 0.0289
fix string #4 ........ 0.0259 ........ 0.0291
8-bit string #1 ...... 0.0284 ........ 0.0394
8-bit string #2 ...... 0.0328 ........ 0.0396
8-bit string #3 ...... 0.0395 ........ 0.0394
16-bit string #1 ..... 0.0443 ........ 0.0480
16-bit string #2 ..... 3.3940 ........ 0.3226
32-bit string ........ 3.3835 ........ 0.3384
wide char string #1 .. 0.0232 ........ 0.0292
wide char string #2 .. 0.0292 ........ 0.0379
8-bit binary #1 ...... 0.0242 ........ 0.0373
8-bit binary #2 ...... 0.0249 ........ 0.0436
8-bit binary #3 ...... 0.0250 ........ 0.0390
16-bit binary ........ 0.0289 ........ 0.0478
32-bit binary ........ 0.3796 ........ 0.3290
fix array #1 ......... 0.0173 ........ 0.0182
fix array #2 ......... 0.0588 ........ 0.0778
16-bit array #1 ...... 0.1637 ........ 0.2004
16-bit array #2 ........... S ............. S
32-bit array .............. S ............. S
complex array ........ 0.2467 ........ 0.3412
fix map #1 ........... 0.1233 ........ 0.1488
fix map #2 ........... 0.0536 ........ 0.0614
fix map #3 ........... 0.0598 ........ 0.0901
fix map #4 ........... 0.0549 ........ 0.0757
16-bit map #1 ........ 0.3068 ........ 0.3498
16-bit map #2 ............. S ............. S
32-bit map ................ S ............. S
complex map .......... 0.3664 ........ 0.4472
fixext 1 ............. 0.0159 ........ 0.0452
fixext 2 ............. 0.0156 ........ 0.0480
fixext 4 ............. 0.0161 ........ 0.0487
fixext 8 ............. 0.0165 ........ 0.0478
fixext 16 ............ 0.0165 ........ 0.0473
8-bit ext ............ 0.0199 ........ 0.0564
16-bit ext ........... 0.0247 ........ 0.0651
32-bit ext ........... 0.3765 ........ 0.3484
=============================================
Total                  9.9493          5.2502
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

```
$ MP_BENCH_TARGETS=pure_ps,pure_bu,pecl_p,pecl_u php -n -dextension=msgpack.so tests/bench.php

Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

================================================================================
Test/Target           Packer (str)  BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------
nil ....................... 0.0058 ........ 0.0203 ...... 0.0067 ........ 0.0051
false ..................... 0.0072 ........ 0.0202 ...... 0.0067 ........ 0.0050
true ...................... 0.0070 ........ 0.0201 ...... 0.0067 ........ 0.0048
7-bit uint #1 ............. 0.0064 ........ 0.0143 ...... 0.0069 ........ 0.0049
7-bit uint #2 ............. 0.0066 ........ 0.0138 ...... 0.0068 ........ 0.0050
7-bit uint #3 ............. 0.0067 ........ 0.0144 ...... 0.0067 ........ 0.0049
5-bit sint #1 ............. 0.0070 ........ 0.0199 ...... 0.0067 ........ 0.0052
5-bit sint #2 ............. 0.0070 ........ 0.0195 ...... 0.0067 ........ 0.0051
5-bit sint #3 ............. 0.0069 ........ 0.0197 ...... 0.0067 ........ 0.0053
8-bit uint #1 ............. 0.0091 ........ 0.0273 ...... 0.0068 ........ 0.0056
8-bit uint #2 ............. 0.0093 ........ 0.0272 ...... 0.0069 ........ 0.0055
8-bit uint #3 ............. 0.0088 ........ 0.0364 ...... 0.0114 ........ 0.0051
16-bit uint #1 ............ 0.0122 ........ 0.0350 ...... 0.0062 ........ 0.0058
16-bit uint #2 ............ 0.0126 ........ 0.0347 ...... 0.0071 ........ 0.0056
16-bit uint #3 ............ 0.0123 ........ 0.0349 ...... 0.0070 ........ 0.0057
32-bit uint #1 ............ 0.0138 ........ 0.0448 ...... 0.0068 ........ 0.0057
32-bit uint #2 ............ 0.0143 ........ 0.0451 ...... 0.0069 ........ 0.0056
32-bit uint #3 ............ 0.0141 ........ 0.0448 ...... 0.0069 ........ 0.0053
64-bit uint #1 ............ 0.0225 ........ 0.0580 ...... 0.0068 ........ 0.0110
64-bit uint #2 ............ 0.0266 ........ 0.0636 ...... 0.0069 ........ 0.0056
8-bit int #1 .............. 0.0094 ........ 0.0307 ...... 0.0068 ........ 0.0055
8-bit int #2 .............. 0.0095 ........ 0.0312 ...... 0.0066 ........ 0.0054
8-bit int #3 .............. 0.0093 ........ 0.0306 ...... 0.0070 ........ 0.0054
16-bit int #1 ............. 0.0124 ........ 0.0352 ...... 0.0068 ........ 0.0054
16-bit int #2 ............. 0.0125 ........ 0.0358 ...... 0.0069 ........ 0.0052
16-bit int #3 ............. 0.0125 ........ 0.0360 ...... 0.0068 ........ 0.0060
32-bit int #1 ............. 0.0144 ........ 0.0467 ...... 0.0070 ........ 0.0052
32-bit int #2 ............. 0.0141 ........ 0.0465 ...... 0.0069 ........ 0.0055
32-bit int #3 ............. 0.0141 ........ 0.0468 ...... 0.0067 ........ 0.0052
64-bit int #1 ............. 0.0222 ........ 0.0551 ...... 0.0069 ........ 0.0056
64-bit int #2 ............. 0.0227 ........ 0.0559 ...... 0.0069 ........ 0.0053
64-bit int #3 ............. 0.0228 ........ 0.0548 ...... 0.0069 ........ 0.0052
64-bit float #1 ........... 0.0181 ........ 0.0469 ...... 0.0067 ........ 0.0055
64-bit float #2 ........... 0.0182 ........ 0.0518 ...... 0.0068 ........ 0.0053
64-bit float #3 ........... 0.0181 ........ 0.0459 ...... 0.0069 ........ 0.0055
fix string #1 ............. 0.0096 ........ 0.0172 ...... 0.0070 ........ 0.0051
fix string #2 ............. 0.0112 ........ 0.0276 ...... 0.0069 ........ 0.0069
fix string #3 ............. 0.0112 ........ 0.0292 ...... 0.0070 ........ 0.0069
fix string #4 ............. 0.0111 ........ 0.0289 ...... 0.0069 ........ 0.0070
8-bit string #1 ........... 0.0138 ........ 0.0395 ...... 0.0068 ........ 0.0066
8-bit string #2 ........... 0.0141 ........ 0.0394 ...... 0.0070 ........ 0.0070
8-bit string #3 ........... 0.0142 ........ 0.0390 ...... 0.0110 ........ 0.0069
16-bit string #1 .......... 0.0176 ........ 0.0482 ...... 0.0111 ........ 0.0073
16-bit string #2 .......... 0.3638 ........ 0.3196 ...... 0.3551 ........ 0.2812
32-bit string ............. 0.3675 ........ 0.3338 ...... 0.3495 ........ 0.2755
wide char string #1 ....... 0.0112 ........ 0.0286 ...... 0.0068 ........ 0.0067
wide char string #2 ....... 0.0137 ........ 0.0387 ...... 0.0069 ........ 0.0067
8-bit binary #1 ................ I ............. I ........... F ............. I
8-bit binary #2 ................ I ............. I ........... F ............. I
8-bit binary #3 ................ I ............. I ........... F ............. I
16-bit binary .................. I ............. I ........... F ............. I
32-bit binary .................. I ............. I ........... F ............. I
fix array #1 .............. 0.0146 ........ 0.0181 ...... 0.0154 ........ 0.0066
fix array #2 .............. 0.0465 ........ 0.0869 ...... 0.0191 ........ 0.0193
16-bit array #1 ........... 0.1562 ........ 0.2314 ...... 0.0322 ........ 0.0445
16-bit array #2 ................ S ............. S ........... S ............. S
32-bit array ................... S ............. S ........... S ............. S
complex array .................. I ............. I ........... F ............. F
fix map #1 ..................... I ............. I ........... F ............. I
fix map #2 ................ 0.0420 ........ 0.0646 ...... 0.0176 ........ 0.0174
fix map #3 ..................... I ............. I ........... F ............. I
fix map #4 ..................... I ............. I ........... F ............. I
16-bit map #1 ............. 0.3017 ........ 0.3882 ...... 0.0332 ........ 0.0660
16-bit map #2 .................. S ............. S ........... S ............. S
32-bit map ..................... S ............. S ........... S ............. S
complex map ............... 0.3216 ........ 0.4773 ...... 0.0686 ........ 0.0694
fixext 1 ....................... I ............. I ........... F ............. F
fixext 2 ....................... I ............. I ........... F ............. F
fixext 4 ....................... I ............. I ........... F ............. F
fixext 8 ....................... I ............. I ........... F ............. F
fixext 16 ...................... I ............. I ........... F ............. F
8-bit ext ...................... I ............. I ........... F ............. F
16-bit ext ..................... I ............. I ........... F ............. F
32-bit ext ..................... I ............. I ........... F ............. F
================================================================================
Total                       2.1882          3.5197        1.2112          1.0396
Skipped                          4               4             4               4
Failed                           0               0            17               9
Ignored                         17              17             0               8
```

> *Note, that this is not a fair comparison as the msgpack extension (0.5.2+, 2.0) doesn't
support **ext**, **bin** and UTF-8 **str** types.*


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
