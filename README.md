# msgpack.php

A pure PHP implementation of the MessagePack serialization format.

[![Build Status](https://travis-ci.org/rybakit/msgpack.php.svg?branch=master)](https://travis-ci.org/rybakit/msgpack.php)
[![Code Coverage](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)


## Features

 * Fully compliant with the latest [MessagePack specification](https://github.com/msgpack/msgpack/blob/master/spec.md),
   including **bin**, **str** and **ext** types
 * Supports [streaming unpacking](#unpacking)
 * Supports [unsigned 64-bit integers handling](#unsigned-64-bit-integers)
 * Supports [object serialization](#custom-types)
 * Works with PHP 5.4-7.0 and HHVM
 * [Fully tested](https://travis-ci.org/rybakit/msgpack.php)
 * [Relatively fast](#performance)


## Table of contents

 * [Installation](#installation)
 * [Usage](#usage)
   * [Packing](#packing)
     * [Type Detection Mode](#type-detection-mode)
   * [Unpacking](#unpacking)
     * [Unsigned 64-bit Integers](#unsigned-64-bit-integers)
 * [Extensions](#extensions)
 * [Custom Types](#custom-types)
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

To pack values use the `Packer` class:

```php
use MessagePack\Packer;

$packer = new Packer();

...

$packed = $packer->pack($value);
```

In the example above, the method `pack` automatically pack a value depending on its type.
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
$packer->packNil();                   // MP nil
$packer->packBool(true);              // MP bool
$packer->packArray([1, 2]);           // MP array
$packer->packMap([1, 2]);             // MP map
$packer->packExt(new Ext(1, "\xaa")); // MP ext
$packer->packFloat(4.2);              // MP float
$packer->packInt(42);                 // MP int
$packer->packStr('foo');              // MP str
$packer->packBin("\x80");             // MP bin
```

> Check ["Custom Types"](#custom-types) section below on how to pack arbitrary PHP objects.


#### Type detection mode

Automatically detecting a MP type of PHP arrays/strings adds some overhead which can be noticed
when you pack large (16- and 32-bit) arrays or strings. However, if you know the variable type
in advance (for example, you only work with utf-8 strings or/and associative arrays), you can
eliminate this overhead by forcing the packer to use the appropriate type, which will save it
from running the auto detection routine:

```php
$packer = new Packer(Packer::FORCE_STR);
// or
...
$packer->setTypeDetectionMode(Packer::FORCE_STR);
...
$packer->pack($utf8string);
```

Available modes are:

```php
Packer::FORCE_STR
Packer::FORCE_BIN
Packer::FORCE_ARR
Packer::FORCE_MAP
```

Of course, you can combine modes:

```php
// convert PHP strings to MP strings, PHP arrays to MP maps
$packer->setTypeDetectionMode(Packer::FORCE_STR | Packer::FORCE_MAP);

// convert PHP strings to MP binaries, PHP arrays to MP arrays
$packer->setTypeDetectionMode(Packer::FORCE_BIN | Packer::FORCE_ARR);

// this will throw \InvalidArgumentException
$packer->setTypeDetectionMode(Packer::FORCE_STR | Packer::FORCE_BIN);
$packer->setTypeDetectionMode(Packer::FORCE_MAP | Packer::FORCE_ARR);
```


### Unpacking

To unpack data use the `BufferUnpacker` class:

```php
use MessagePack\BufferUnpacker;

$unpacker = new BufferUnpacker();

...

$unpacker->reset($data);
$unpacked = $unpacker->unpack();
```

If the packed data is received in chunks (e.g. when reading from a stream), use the `tryUnpack`
method, which will try to unpack data and return an array of unpacked data instead of throwing a `InsufficientDataException`:

```php
$unpacker->append($chunk1);
$unpackedBlocks = $unpacker->tryUnpack();

$unpacker->append($chunk2);
$unpackedBlocks = $unpacker->tryUnpack();
```

To save some keystrokes, the library ships with a syntax sugar class `Unpacker`, which
is no more than a tiny wrapper around `BufferUnpacker` with a single method `unpack`:

```php
use MessagePack\Unpacker;

...

$unpacked = (new Unpacker())->unpack($data);
```


#### Unsigned 64-bit Integers

The binary MessagePack format has unsigned 64-bit as its largest integer data type,
but PHP does not support such integers. By default, while unpacking `uint64` value
the library will throw a `IntegerOverflowException`.

You can change this default behavior to unpack `uint64` integer to a string:

```php
$unpacker->setIntOverflowMode(BufferUnpacker::INT_AS_STRING);
```

Or to a `Gmp` number (make sure that [gmp](http://php.net/manual/en/book.gmp.php) extension is enabled):

```php
$unpacker->setIntOverflowMode(BufferUnpacker::INT_AS_GMP);
```


### Extensions

To define application-specific types use the `Ext` class:

```php
use MessagePack\Ext;
use MessagePack\Packer;
use MessagePack\Unpacker;

$packerd = (new Packer())->pack(new Ext(42, "\xaa"));
$ext = (new Unpacker())->unpack($packed);

$extType = $ext->getType(); // 42
$extData = $ext->getData(); // "\xaa"
```


### Custom Types

In addition to [the basic types](https://github.com/msgpack/msgpack/blob/master/spec.md#type-system),
the library provides the functionality to serialize and deserialize arbitrary types.
To do this, you need to create a transformer, that converts your type to a type, which can be handled by MessagePack.

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
$raw = $unpacker->reset($packed)->unpack();
```


## Exceptions

If an error occurs during packing/unpacking, a `PackingFailedException` or `UnpackingFailedException`
will be thrown, respectively.

In addition, there are two more exceptions that can be thrown during unpacking:

 * `InsufficientDataException`
 * `IntegerOverflowException`


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

The command above will create a container named `msgpack` with PHP 5.6 runtime.
You may change the default runtime by defining the `PHP_RUNTIME` environment variable:

```sh
$ PHP_RUNTIME='php:7.0-cli' ./dockerfile.sh | docker build -t msgpack -
```

> See a list of various runtimes [here](.travis.yml#L9-L15).

Then run the unit tests:

```sh
$ docker run --rm --name msgpack -v $(pwd):/msgpack -w /msgpack msgpack
```


#### Performance

To check the performance run:

```sh
$ php tests/bench.php
```

This command will output something like:

```
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

=============================================
Test/Target            Packer  BufferUnpacker
---------------------------------------------
nil .................. 0.0076 ........ 0.0230
false ................ 0.0087 ........ 0.0254
true ................. 0.0081 ........ 0.0258
7-bit uint #1 ........ 0.0105 ........ 0.0189
7-bit uint #2 ........ 0.0095 ........ 0.0193
7-bit uint #3 ........ 0.0094 ........ 0.0202
5-bit sint #1 ........ 0.0105 ........ 0.0254
5-bit sint #2 ........ 0.0112 ........ 0.0242
5-bit sint #3 ........ 0.0119 ........ 0.0231
8-bit uint #1 ........ 0.0122 ........ 0.0451
8-bit uint #2 ........ 0.0128 ........ 0.0438
8-bit uint #3 ........ 0.0119 ........ 0.0464
16-bit uint #1 ....... 0.0177 ........ 0.0540
16-bit uint #2 ....... 0.0180 ........ 0.0566
16-bit uint #3 ....... 0.0176 ........ 0.0554
32-bit uint #1 ....... 0.0226 ........ 0.0708
32-bit uint #2 ....... 0.0206 ........ 0.0684
32-bit uint #3 ....... 0.0218 ........ 0.0686
64-bit uint #1 ....... 0.0332 ........ 0.0818
64-bit uint #2 ....... 0.0350 ........ 0.0826
8-bit int #1 ......... 0.0140 ........ 0.0516
8-bit int #2 ......... 0.0138 ........ 0.0483
8-bit int #3 ......... 0.0131 ........ 0.0504
16-bit int #1 ........ 0.0206 ........ 0.0577
16-bit int #2 ........ 0.0199 ........ 0.0571
16-bit int #3 ........ 0.0199 ........ 0.0576
32-bit int #1 ........ 0.0225 ........ 0.0781
32-bit int #2 ........ 0.0240 ........ 0.0769
32-bit int #3 ........ 0.0239 ........ 0.0773
64-bit int #1 ........ 0.0327 ........ 0.0847
64-bit int #2 ........ 0.0324 ........ 0.0829
64-bit int #3 ........ 0.0371 ........ 0.0842
64-bit float #1 ...... 0.0287 ........ 0.0711
64-bit float #2 ...... 0.0279 ........ 0.0703
64-bit float #3 ...... 0.0279 ........ 0.0703
fix string #1 ........ 0.0255 ........ 0.0248
fix string #2 ........ 0.0256 ........ 0.0427
fix string #3 ........ 0.0262 ........ 0.0428
fix string #4 ........ 0.0282 ........ 0.0411
8-bit string #1 ...... 0.0342 ........ 0.0682
8-bit string #2 ...... 0.0351 ........ 0.0699
8-bit string #3 ...... 0.0413 ........ 0.0742
16-bit string #1 ..... 0.0507 ........ 0.0767
16-bit string #2 ..... 3.1860 ........ 0.3319
32-bit string ........ 3.1942 ........ 0.3502
wide char string #1 .. 0.0333 ........ 0.0416
wide char string #2 .. 0.0887 ........ 0.0704
8-bit binary #1 ...... 0.0289 ........ 0.0608
8-bit binary #2 ...... 0.0321 ........ 0.0602
8-bit binary #3 ...... 0.0308 ........ 0.0607
16-bit binary ........ 0.0379 ........ 0.0732
32-bit binary ........ 0.3814 ........ 0.3507
fixext 1 ............. 0.0256 ........ 0.0909
fixext 2 ............. 0.0258 ........ 0.0883
fixext 4 ............. 0.0264 ........ 0.0910
fixext 8 ............. 0.0279 ........ 0.0898
fixext 16 ............ 0.0290 ........ 0.0900
8-bit ext ............ 0.0338 ........ 0.1077
16-bit ext ........... 0.0431 ........ 0.1179
32-bit ext ........... 0.3784 ........ 0.3879
fix array #1 ......... 0.0243 ........ 0.0277
fix array #2 ......... 0.0857 ........ 0.1124
16-bit array #1 ...... 0.2455 ........ 0.3539
16-bit array #2 ........... S ............. S
32-bit array .............. S ............. S
complex array ........ 0.3501 ........ 0.5267
fix map #1 ........... 0.1639 ........ 0.2414
fix map #2 ........... 0.0687 ........ 0.0897
fix map #3 ........... 0.0820 ........ 0.1379
fix map #4 ........... 0.0806 ........ 0.1066
16-bit map #1 ........ 0.4314 ........ 0.6155
16-bit map #2 ............. S ............. S
32-bit map ................ S ............. S
complex map .......... 0.5023 ........ 0.6408
=============================================
Total                 10.5740          7.6534
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
$ # or an regexp
$ # export MP_BENCH_TESTS='/complex (array|map)/'
$ php tests/bench.php
```

Another example, benchmarking both the library and [msgpack pecl extension](https://pecl.php.net/package/msgpack):

```
$ MP_BENCH_TARGETS=pure_ps,pure_bu,pecl_p,pecl_u php tests/bench.php

Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

================================================================================
Test/Target           Packer (str)  BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------
nil ....................... 0.0080 ........ 0.0243 ...... 0.0078 ........ 0.0061
false ..................... 0.0093 ........ 0.0249 ...... 0.0075 ........ 0.0061
true ...................... 0.0078 ........ 0.0249 ...... 0.0083 ........ 0.0062
7-bit uint #1 ............. 0.0097 ........ 0.0193 ...... 0.0081 ........ 0.0061
7-bit uint #2 ............. 0.0097 ........ 0.0195 ...... 0.0081 ........ 0.0067
7-bit uint #3 ............. 0.0099 ........ 0.0194 ...... 0.0081 ........ 0.0061
5-bit sint #1 ............. 0.0109 ........ 0.0249 ...... 0.0074 ........ 0.0060
5-bit sint #2 ............. 0.0108 ........ 0.0232 ...... 0.0082 ........ 0.0060
5-bit sint #3 ............. 0.0110 ........ 0.0248 ...... 0.0082 ........ 0.0059
8-bit uint #1 ............. 0.0125 ........ 0.0473 ...... 0.0087 ........ 0.0072
8-bit uint #2 ............. 0.0125 ........ 0.0484 ...... 0.0082 ........ 0.0064
8-bit uint #3 ............. 0.0123 ........ 0.0454 ...... 0.0082 ........ 0.0063
16-bit uint #1 ............ 0.0176 ........ 0.0543 ...... 0.0092 ........ 0.0065
16-bit uint #2 ............ 0.0170 ........ 0.0548 ...... 0.0083 ........ 0.0066
16-bit uint #3 ............ 0.0175 ........ 0.0559 ...... 0.0083 ........ 0.0077
32-bit uint #1 ............ 0.0215 ........ 0.0694 ...... 0.0082 ........ 0.0067
32-bit uint #2 ............ 0.0216 ........ 0.0705 ...... 0.0082 ........ 0.0061
32-bit uint #3 ............ 0.0213 ........ 0.0698 ...... 0.0094 ........ 0.0075
64-bit uint #1 ............ 0.0323 ........ 0.0812 ...... 0.0095 ........ 0.0066
64-bit uint #2 ............ 0.0321 ........ 0.0861 ...... 0.0089 ........ 0.0064
8-bit int #1 .............. 0.0126 ........ 0.0494 ...... 0.0084 ........ 0.0065
8-bit int #2 .............. 0.0142 ........ 0.0499 ...... 0.0082 ........ 0.0066
8-bit int #3 .............. 0.0136 ........ 0.0489 ...... 0.0083 ........ 0.0065
16-bit int #1 ............. 0.0175 ........ 0.0593 ...... 0.0081 ........ 0.0065
16-bit int #2 ............. 0.0177 ........ 0.0603 ...... 0.0074 ........ 0.0074
16-bit int #3 ............. 0.0178 ........ 0.0602 ...... 0.0081 ........ 0.0069
32-bit int #1 ............. 0.0221 ........ 0.0789 ...... 0.0079 ........ 0.0073
32-bit int #2 ............. 0.0233 ........ 0.0785 ...... 0.0097 ........ 0.0072
32-bit int #3 ............. 0.0214 ........ 0.0767 ...... 0.0082 ........ 0.0063
64-bit int #1 ............. 0.0318 ........ 0.0887 ...... 0.0083 ........ 0.0077
64-bit int #2 ............. 0.0321 ........ 0.0871 ...... 0.0083 ........ 0.0064
64-bit int #3 ............. 0.0332 ........ 0.0836 ...... 0.0083 ........ 0.0063
64-bit float #1 ........... 0.0288 ........ 0.0759 ...... 0.0084 ........ 0.0062
64-bit float #2 ........... 0.0278 ........ 0.0726 ...... 0.0078 ........ 0.0072
64-bit float #3 ........... 0.0287 ........ 0.0755 ...... 0.0078 ........ 0.0063
fix string #1 ............. 0.0164 ........ 0.0244 ...... 0.0085 ........ 0.0063
fix string #2 ............. 0.0184 ........ 0.0411 ...... 0.0085 ........ 0.0082
fix string #3 ............. 0.0182 ........ 0.0419 ...... 0.0100 ........ 0.0088
fix string #4 ............. 0.0181 ........ 0.0406 ...... 0.0084 ........ 0.0079
8-bit string #1 ........... 0.0209 ........ 0.0677 ...... 0.0108 ........ 0.0076
8-bit string #2 ........... 0.0207 ........ 0.0672 ...... 0.0086 ........ 0.0079
8-bit string #3 ........... 0.0221 ........ 0.0677 ...... 0.0132 ........ 0.0082
16-bit string #1 .......... 0.0263 ........ 0.0774 ...... 0.0132 ........ 0.0089
16-bit string #2 .......... 0.3639 ........ 0.3378 ...... 0.3470 ........ 0.2731
32-bit string ............. 0.3733 ........ 0.3516 ...... 0.3426 ........ 0.2705
wide char string #1 ....... 0.0180 ........ 0.0400 ...... 0.0085 ........ 0.0078
wide char string #2 ....... 0.0203 ........ 0.0675 ...... 0.0086 ........ 0.0081
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
fix array #1 .............. 0.0269 ........ 0.0266 ...... 0.0170 ........ 0.0080
fix array #2 .............. 0.0764 ........ 0.1063 ...... 0.0218 ........ 0.0213
16-bit array #1 ........... 0.2474 ........ 0.3291 ...... 0.0424 ........ 0.0482
16-bit array #2 ................ S ............. S ........... S ............. S
32-bit array ................... S ............. S ........... S ............. S
complex array .................. I ............. I ........... F ............. F
fix map #1 ..................... I ............. I ........... F ............. I
fix map #2 ................ 0.0664 ........ 0.0821 ...... 0.0189 ........ 0.0207
fix map #3 ..................... I ............. I ........... F ............. I
fix map #4 ..................... I ............. I ........... F ............. I
16-bit map #1 ............. 0.4268 ........ 0.5937 ...... 0.0400 ........ 0.0696
16-bit map #2 .................. S ............. S ........... S ............. S
32-bit map ..................... S ............. S ........... S ............. S
complex map ............... 0.4846 ........ 0.6386 ...... 0.0686 ........ 0.0778
================================================================================
Total                       2.9131          4.9354        1.2862          1.0997
Skipped                          4               4             4               4
Failed                           0               0            17               9
Ignored                         17              17             0               8
```

> Note, that this is not a fair comparison as the msgpack extension (0.5.2+, 2.0) doesn't
support **ext**, **bin** and utf-8 **str** types.


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
