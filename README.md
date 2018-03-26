# msgpack.php

A pure PHP implementation of the MessagePack serialization format.

[![Build Status](https://travis-ci.org/rybakit/msgpack.php.svg?branch=master)](https://travis-ci.org/rybakit/msgpack.php)
[![Code Coverage](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)


## Features

 * Fully compliant with the latest [MessagePack specification](https://github.com/msgpack/msgpack/blob/master/spec.md),
   including **bin**, **str** and **ext** types
 * Supports [streaming unpacking](#unpacking)
 * Supports [unsigned 64-bit integers handling](#unpacking-options)
 * Supports [object serialization](#custom-types)
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
 * [Custom types](#custom-types)
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

To pack values you can either use an instance of `Packer`:

```php
use MessagePack\Packer;

$packer = new Packer();

...

$packed = $packer->pack($value);
```

or call the static method on the `MessagePack` class:

```php
use MessagePack\MessagePack;

...

$packed = MessagePack::pack($value);
```

In the examples above, the method `pack` automatically packs a value depending on its type.
But not all PHP types can be uniquely translated to MessagePack types. For example,
MessagePack format defines `map` and `array` types, which are represented by a single `array`
type in PHP. By default, the packer will pack a PHP array as a MessagePack array if it
has sequential numeric keys, starting from `0` and as a MessagePack map otherwise:

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

Here is a list of all low-level packer methods:

```php
$packer->packArray([1, 2]);           // MP array
$packer->packMap([1, 2]);             // MP map
$packer->packStr('foo');              // MP str
$packer->packBin("\x80");             // MP bin
$packer->packFloat32(M_PI);           // MP float 32
$packer->packFloat64(M_PI);           // MP float 64
$packer->packInt(42);                 // MP int
$packer->packNil();                   // MP nil
$packer->packBool(true);              // MP bool
$packer->packExt(new Ext(1, "\xaa")); // MP ext
```

> *Check ["Custom types"](#custom-types) section below on how to pack arbitrary PHP objects.*


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

> *Automatically detecting which MessagePack type to use to pack a value (the `DETECT_STR_BIN`/`DETECT_ARR_MAP` mode) 
> adds some overhead which can be noticed when you pack large (16- and 32-bit) arrays or strings.
> However, if you know the value type in advance (for example, you only work with UTF-8 strings 
> or/and associative arrays), you can eliminate this overhead by forcing the packer to use 
> the appropriate type, which will save it from running the auto detection routine.*

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

To unpack data you can either use an instance of `BufferUnpacker`:

```php
use MessagePack\BufferUnpacker;

$unpacker = new BufferUnpacker();

...

$unpacker->reset($packed);
$value = $unpacker->unpack();
```

or call the static method on the `MessagePack` class:

```php
use MessagePack\MessagePack;

...

$value = MessagePack::unpack($packed);
```

If the packed data is received in chunks (e.g. when reading from a stream), use the `tryUnpack`
method, which attempts to unpack data and returns an array of unpacked messages (if any) instead of throwing an `InsufficientDataException`:

```php
while ($chunk = ...) {
    $unpacker->append($chunk);
    if ($messages = $unpacker->tryUnpack()) {
        return $messages;
    }
}
```


#### Unpacking options

The `BufferUnpacker` object supports a number of bitmask-based options for fine-tuning 
the unpacking process (defaults are in bold):

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

$extType = $ext->getType(); // 42
$extData = $ext->getData(); // "\xaa"
```


### Custom types

In addition to [the basic types](https://github.com/msgpack/msgpack/blob/master/spec.md#type-system),
the library provides functionality to serialize and deserialize arbitrary types. To do this, you need 
to create a transformer, that converts your type to a type, which can be handled by MessagePack.

For example, the code below shows how to add `DateTime` object support:

```php
use MessagePack\TypeTransformer\TypeTransformer;

class DateTimeTransformer implements TypeTransformer
{
    private $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function supports($value)
    {
        return $value instanceof \DateTime;
    }

    public function transform($value)
    {
        return $value->format(\DateTime::RFC3339);
    }

    public function reverseTransform($data)
    {
        return new \DateTime($data);
    }
}
```

```php
use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\TypeTransformer\Collection;

$packer = new Packer();
$unpacker = new BufferUnpacker();

$coll = new Collection([new DateTimeTransformer(5)]);
// $coll->add(new AnotherTypeTransformer(42));

$packer->setTransformers($coll);
$unpacker->setTransformers($coll);

$packed = $packer->pack(['foo' => new DateTime(), 'bar' => 'baz']);
$value = $unpacker->reset($packed)->unpack();
```


## Exceptions

If an error occurs during packing/unpacking, a `PackingFailedException` or `UnpackingFailedException`
will be thrown, respectively.

In addition, there are two more exceptions that can be thrown during unpacking:

 * `InsufficientDataException`
 * `IntegerOverflowException`

The `InvalidOptionException` will be thrown in case of an invalid option (or a combination 
of mutually exclusive options) is used.


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
nil .................. 0.0064 ........ 0.0214
false ................ 0.0073 ........ 0.0215
true ................. 0.0077 ........ 0.0217
7-bit uint #1 ........ 0.0088 ........ 0.0162
7-bit uint #2 ........ 0.0085 ........ 0.0163
7-bit uint #3 ........ 0.0090 ........ 0.0159
5-bit sint #1 ........ 0.0091 ........ 0.0205
5-bit sint #2 ........ 0.0090 ........ 0.0206
5-bit sint #3 ........ 0.0092 ........ 0.0209
8-bit uint #1 ........ 0.0112 ........ 0.0341
8-bit uint #2 ........ 0.0112 ........ 0.0337
8-bit uint #3 ........ 0.0118 ........ 0.0353
16-bit uint #1 ....... 0.0166 ........ 0.0433
16-bit uint #2 ....... 0.0166 ........ 0.0445
16-bit uint #3 ....... 0.0168 ........ 0.0434
32-bit uint #1 ....... 0.0185 ........ 0.0546
32-bit uint #2 ....... 0.0186 ........ 0.0542
32-bit uint #3 ....... 0.0193 ........ 0.0546
64-bit uint #1 ....... 0.0273 ........ 0.0667
64-bit uint #2 ....... 0.0272 ........ 0.0662
8-bit int #1 ......... 0.0111 ........ 0.0372
8-bit int #2 ......... 0.0111 ........ 0.0376
8-bit int #3 ......... 0.0117 ........ 0.0370
16-bit int #1 ........ 0.0163 ........ 0.0455
16-bit int #2 ........ 0.0162 ........ 0.0454
16-bit int #3 ........ 0.0164 ........ 0.0455
32-bit int #1 ........ 0.0186 ........ 0.0625
32-bit int #2 ........ 0.0185 ........ 0.0621
32-bit int #3 ........ 0.0189 ........ 0.0622
64-bit int #1 ........ 0.0274 ........ 0.0690
64-bit int #2 ........ 0.0271 ........ 0.0685
64-bit int #3 ........ 0.0273 ........ 0.0701
64-bit float #1 ...... 0.0245 ........ 0.0583
64-bit float #2 ...... 0.0247 ........ 0.0585
64-bit float #3 ...... 0.0247 ........ 0.0608
fix string #1 ........ 0.0251 ........ 0.0208
fix string #2 ........ 0.0257 ........ 0.0318
fix string #3 ........ 0.0259 ........ 0.0312
fix string #4 ........ 0.0286 ........ 0.0310
8-bit string #1 ...... 0.0314 ........ 0.0521
8-bit string #2 ...... 0.0363 ........ 0.0534
8-bit string #3 ...... 0.0433 ........ 0.0534
16-bit string #1 ..... 0.0489 ........ 0.0607
16-bit string #2 ..... 3.4097 ........ 0.3329
32-bit string ........ 3.4152 ........ 0.3445
wide char string #1 .. 0.0272 ........ 0.0308
wide char string #2 .. 0.0329 ........ 0.0533
8-bit binary #1 ...... 0.0274 ........ 0.0460
8-bit binary #2 ...... 0.0278 ........ 0.0469
8-bit binary #3 ...... 0.0284 ........ 0.0458
16-bit binary ........ 0.0333 ........ 0.0548
32-bit binary ........ 0.3873 ........ 0.3416
fixext 1 ............. 0.0222 ........ 0.0655
fixext 2 ............. 0.0230 ........ 0.0661
fixext 4 ............. 0.0238 ........ 0.0668
fixext 8 ............. 0.0251 ........ 0.0672
fixext 16 ............ 0.0255 ........ 0.0680
8-bit ext ............ 0.0318 ........ 0.0781
16-bit ext ........... 0.0360 ........ 0.0899
32-bit ext ........... 0.3880 ........ 0.3685
fix array #1 ......... 0.0227 ........ 0.0227
fix array #2 ......... 0.0796 ........ 0.0836
16-bit array #1 ...... 0.2355 ........ 0.2562
16-bit array #2 ........... S ............. S
32-bit array .............. S ............. S
complex array ........ 0.3323 ........ 0.3892
fix map #1 ........... 0.1533 ........ 0.1844
fix map #2 ........... 0.0648 ........ 0.0654
fix map #3 ........... 0.0785 ........ 0.1119
fix map #4 ........... 0.0726 ........ 0.0871
16-bit map #1 ........ 0.3779 ........ 0.4690
16-bit map #2 ............. S ............. S
32-bit map ................ S ............. S
complex map .......... 0.4673 ........ 0.5231
=============================================
Total                 10.6791          6.2192
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

Another example, benchmarking both the library and [msgpack pecl extension](https://pecl.php.net/package/msgpack):

```
$ MP_BENCH_TARGETS=pure_ps,pure_bu,pecl_p,pecl_u php -n -dextension=msgpack.so tests/bench.php

Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

================================================================================
Test/Target           Packer (str)  BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------
nil ....................... 0.0063 ........ 0.0199 ...... 0.0068 ........ 0.0053
false ..................... 0.0076 ........ 0.0210 ...... 0.0069 ........ 0.0054
true ...................... 0.0074 ........ 0.0211 ...... 0.0069 ........ 0.0054
7-bit uint #1 ............. 0.0088 ........ 0.0157 ...... 0.0074 ........ 0.0053
7-bit uint #2 ............. 0.0087 ........ 0.0154 ...... 0.0071 ........ 0.0054
7-bit uint #3 ............. 0.0087 ........ 0.0152 ...... 0.0072 ........ 0.0057
5-bit sint #1 ............. 0.0089 ........ 0.0200 ...... 0.0073 ........ 0.0057
5-bit sint #2 ............. 0.0091 ........ 0.0198 ...... 0.0073 ........ 0.0056
5-bit sint #3 ............. 0.0088 ........ 0.0197 ...... 0.0071 ........ 0.0053
8-bit uint #1 ............. 0.0109 ........ 0.0328 ...... 0.0073 ........ 0.0059
8-bit uint #2 ............. 0.0109 ........ 0.0327 ...... 0.0073 ........ 0.0058
8-bit uint #3 ............. 0.0112 ........ 0.0327 ...... 0.0074 ........ 0.0061
16-bit uint #1 ............ 0.0158 ........ 0.0426 ...... 0.0073 ........ 0.0061
16-bit uint #2 ............ 0.0161 ........ 0.0437 ...... 0.0071 ........ 0.0062
16-bit uint #3 ............ 0.0157 ........ 0.0425 ...... 0.0072 ........ 0.0059
32-bit uint #1 ............ 0.0179 ........ 0.0538 ...... 0.0074 ........ 0.0061
32-bit uint #2 ............ 0.0179 ........ 0.0550 ...... 0.0073 ........ 0.0062
32-bit uint #3 ............ 0.0186 ........ 0.0535 ...... 0.0079 ........ 0.0061
64-bit uint #1 ............ 0.0288 ........ 0.0661 ...... 0.0071 ........ 0.0060
64-bit uint #2 ............ 0.0264 ........ 0.0657 ...... 0.0074 ........ 0.0063
8-bit int #1 .............. 0.0108 ........ 0.0369 ...... 0.0072 ........ 0.0061
8-bit int #2 .............. 0.0112 ........ 0.0362 ...... 0.0072 ........ 0.0061
8-bit int #3 .............. 0.0112 ........ 0.0364 ...... 0.0072 ........ 0.0071
16-bit int #1 ............. 0.0161 ........ 0.0450 ...... 0.0072 ........ 0.0060
16-bit int #2 ............. 0.0158 ........ 0.0442 ...... 0.0070 ........ 0.0062
16-bit int #3 ............. 0.0157 ........ 0.0450 ...... 0.0070 ........ 0.0062
32-bit int #1 ............. 0.0184 ........ 0.0620 ...... 0.0074 ........ 0.0060
32-bit int #2 ............. 0.0182 ........ 0.0609 ...... 0.0071 ........ 0.0063
32-bit int #3 ............. 0.0181 ........ 0.0612 ...... 0.0071 ........ 0.0061
64-bit int #1 ............. 0.0267 ........ 0.0672 ...... 0.0072 ........ 0.0061
64-bit int #2 ............. 0.0265 ........ 0.0678 ...... 0.0073 ........ 0.0063
64-bit int #3 ............. 0.0271 ........ 0.0688 ...... 0.0071 ........ 0.0060
64-bit float #1 ........... 0.0239 ........ 0.0578 ...... 0.0073 ........ 0.0061
64-bit float #2 ........... 0.0246 ........ 0.0573 ...... 0.0070 ........ 0.0063
64-bit float #3 ........... 0.0239 ........ 0.0582 ...... 0.0071 ........ 0.0060
fix string #1 ............. 0.0122 ........ 0.0203 ...... 0.0074 ........ 0.0058
fix string #2 ............. 0.0132 ........ 0.0304 ...... 0.0073 ........ 0.0076
fix string #3 ............. 0.0150 ........ 0.0310 ...... 0.0075 ........ 0.0077
fix string #4 ............. 0.0140 ........ 0.0307 ...... 0.0074 ........ 0.0075
8-bit string #1 ........... 0.0165 ........ 0.0522 ...... 0.0073 ........ 0.0072
8-bit string #2 ........... 0.0178 ........ 0.0518 ...... 0.0079 ........ 0.0074
8-bit string #3 ........... 0.0169 ........ 0.0518 ...... 0.0123 ........ 0.0074
16-bit string #1 .......... 0.0221 ........ 0.0622 ...... 0.0123 ........ 0.0080
16-bit string #2 .......... 0.3719 ........ 0.3319 ...... 0.3592 ........ 0.2849
32-bit string ............. 0.3805 ........ 0.3474 ...... 0.3623 ........ 0.2800
wide char string #1 ....... 0.0139 ........ 0.0302 ...... 0.0075 ........ 0.0075
wide char string #2 ....... 0.0164 ........ 0.0516 ...... 0.0079 ........ 0.0073
8-bit binary #1 ................ I ............. I ........... F ............. I
8-bit binary #2 ................ I ............. I ........... F ............. I
8-bit binary #3 ................ I ............. I ........... F ............. I
16-bit binary .................. I ............. I ........... F ............. I
32-bit binary .................. I ............. I ........... F ............. I
fixext 1 ....................... I ............. I ........... F ............. F
fixext 2 ....................... I ............. I ........... F ............. F
fixext 4 ....................... I ............. I ........... F ............. F
fixext 8 ....................... I ............. I ........... F ............. F
fixext 16 ...................... I ............. I ........... F ............. F
8-bit ext ...................... I ............. I ........... F ............. F
16-bit ext ..................... I ............. I ........... F ............. F
32-bit ext ..................... I ............. I ........... F ............. F
fix array #1 .............. 0.0227 ........ 0.0222 ...... 0.0156 ........ 0.0070
fix array #2 .............. 0.0679 ........ 0.0820 ...... 0.0209 ........ 0.0197
16-bit array #1 ........... 0.2360 ........ 0.2429 ...... 0.0334 ........ 0.0442
16-bit array #2 ................ S ............. S ........... S ............. S
32-bit array ................... S ............. S ........... S ............. S
complex array .................. I ............. I ........... F ............. F
fix map #1 ..................... I ............. I ........... F ............. I
fix map #2 ................ 0.0534 ........ 0.0659 ...... 0.0183 ........ 0.0188
fix map #3 ..................... I ............. I ........... F ............. I
fix map #4 ..................... I ............. I ........... F ............. I
16-bit map #1 ............. 0.3979 ........ 0.4340 ...... 0.0342 ........ 0.0662
16-bit map #2 .................. S ............. S ........... S ............. S
32-bit map ..................... S ............. S ........... S ............. S
complex map ............... 0.4171 ........ 0.5042 ...... 0.0678 ........ 0.0708
================================================================================
Total                       2.6581          3.9364        1.2488          1.0739
Skipped                          4               4             4               4
Failed                           0               0            17               9
Ignored                         17              17             0               8
```

> *Note, that this is not a fair comparison as the msgpack extension (0.5.2+, 2.0) doesn't
support **ext**, **bin** and UTF-8 **str** types.*


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
