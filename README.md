# msgpack.php

A pure PHP implementation of the MessagePack serialization format.

[![Build Status](https://travis-ci.org/rybakit/msgpack.php.svg?branch=master)](https://travis-ci.org/rybakit/msgpack.php)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)
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
$packer->packDouble(4.2);             // MP float
$packer->packInt(42);                 // MP int
$packer->packStr('foo');              // MP str
$packer->packBin("\x80");             // MP bin
```

> Check ["Custom Types"](#custom-types) section below on how to pack arbitrary PHP objects.



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
method, which will try unpack data and return an array of unpacked data instead of throwing a `InsufficientDataException`:

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
        return $value->getTimestamp();
    }

    public function reverseTransform($timestamp)
    {
        return new \DateTime('@'.$timestamp);
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

> See a list of various runtimes [here](.travis.yml#L9-L13).

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

```sh
Filter: MessagePack\Tests\Perf\Filter\NameFilter
Rounds: 3
Iterations: 100000

====================================================================
Test/Target           MessagePack\Packer  MessagePack\BufferUnpacker
--------------------------------------------------------------------
nil ............................. 0.0168 .................... 0.0264
false ........................... 0.0174 .................... 0.0274
true ............................ 0.0171 .................... 0.0277
7-bit uint #1 ................... 0.0190 .................... 0.0226
7-bit uint #2 ................... 0.0186 .................... 0.0204
7-bit uint #3 ................... 0.0174 .................... 0.0210
5-bit sint #1 ................... 0.0196 .................... 0.0255
5-bit sint #2 ................... 0.0182 .................... 0.0246
5-bit sint #3 ................... 0.0185 .................... 0.0247
8-bit uint #1 ................... 0.0300 .................... 0.0688
8-bit uint #2 ................... 0.0306 .................... 0.0663
8-bit uint #3 ................... 0.0291 .................... 0.0634
16-bit uint #1 .................. 0.0296 .................... 0.0706
16-bit uint #2 .................. 0.0284 .................... 0.0752
16-bit uint #3 .................. 0.0299 .................... 0.0714
32-bit uint #1 .................. 0.0309 .................... 0.0726
32-bit uint #2 .................. 0.0293 .................... 0.0713
32-bit uint #3 .................. 0.0291 .................... 0.0720
64-bit uint #1 .................. 0.0401 .................... 0.0907
64-bit uint #2 .................. 0.0423 .................... 0.0899
8-bit int #1 .................... 0.0294 .................... 0.0731
8-bit int #2 .................... 0.0279 .................... 0.0680
8-bit int #3 .................... 0.0304 .................... 0.0669
16-bit int #1 ................... 0.0285 .................... 0.0789
16-bit int #2 ................... 0.0295 .................... 0.0833
16-bit int #3 ................... 0.0297 .................... 0.0798
32-bit int #1 ................... 0.0317 .................... 0.0833
32-bit int #2 ................... 0.0304 .................... 0.0780
32-bit int #3 ................... 0.0297 .................... 0.0820
64-bit int #1 ................... 0.0415 .................... 0.0922
64-bit int #2 ................... 0.0420 .................... 0.0868
64-bit int #3 ................... 0.0423 .................... 0.0917
64-bit float #1 ................. 0.0354 .................... 0.0771
64-bit float #2 ................. 0.0378 .................... 0.0744
64-bit float #3 ................. 0.0378 .................... 0.0769
fix string #1 ................... 0.0422 .................... 0.0265
fix string #2 ................... 0.0403 .................... 0.0417
fix string #3 ................... 0.0416 .................... 0.0438
fix string #4 ................... 0.0463 .................... 0.0411
8-bit string #1 ................. 0.0457 .................... 0.0930
8-bit string #2 ................. 0.0502 .................... 0.0917
8-bit string #3 ................. 0.0564 .................... 0.0935
16-bit string #1 ................ 0.0564 .................... 0.0902
16-bit string #2 ................ 3.2955 .................... 0.3509
32-bit string ................... 3.2850 .................... 0.3709
wide char string #1 ............. 0.0470 .................... 0.0430
wide char string #2 ............. 0.1077 .................... 0.0880
8-bit binary #1 ................. 0.0356 .................... 0.0832
8-bit binary #2 ................. 0.0473 .................... 0.0822
8-bit binary #3 ................. 0.0456 .................... 0.0830
16-bit binary ................... 0.0480 .................... 0.0908
32-bit binary ................... 0.3961 .................... 0.3591
fixext 1 ........................ 0.0439 .................... 0.1063
fixext 2 ........................ 0.0429 .................... 0.1131
fixext 4 ........................ 0.0444 .................... 0.1134
fixext 8 ........................ 0.0461 .................... 0.1087
fixext 16 ....................... 0.0446 .................... 0.1084
8-bit ext ....................... 0.0516 .................... 0.1447
16-bit ext ...................... 0.0481 .................... 0.1578
32-bit ext ...................... 0.4013 .................... 0.4367
fix array #1 .................... 0.0329 .................... 0.0319
fix array #2 .................... 0.1400 .................... 0.1140
16-bit array #1 ................. 0.4411 .................... 0.3772
16-bit array #2 ...................... S ......................... S
32-bit array ......................... S ......................... S
complex array ................... 0.5326 .................... 0.6465
fix map #1 ...................... 0.2713 .................... 0.2674
fix map #2 ...................... 0.1067 .................... 0.0901
fix map #3 ...................... 0.1100 .................... 0.1981
fix map #4 ...................... 0.1283 .................... 0.1112
16-bit map #1 ................... 0.7501 .................... 0.6892
16-bit map #2 ........................ S ......................... S
32-bit map ........................... S ......................... S
complex map ..................... 0.7716 .................... 0.7159
====================================================================
Total                            12.7107                      8.8278
Skipped                                4                           4
Failed                                 0                           0
Ignored                                0                           0
```

You may change default benchmark settings by defining the following environment variables:

 * `MP_BENCH_TARGETS` (pure_p, pure_u, pecl_p, pecl_u)
 * `MP_BENCH_ITERATIONS`/`MP_BENCH_DURATION`
 * `MP_BENCH_ROUNDS`
 * `MP_BENCH_TESTS`

For example:

```sh
$ export MP_BENCH_TARGET=pure_p
$ export MP_BENCH_ITERATIONS=1000000
$ export MP_BENCH_ROUNDS=5
$ export MP_BENCH_TESTS='complex array, complex map'
$ php tests/bench.php
```

Another example, benchmarking both the library and [msgpack pecl extension](https://pecl.php.net/package/msgpack):

```sh
$ MP_BENCH_TARGETS=pure_p,pure_u,pecl_p,pecl_u php tests/bench.php

Filter: MessagePack\Tests\Perf\Filter\NameFilter
Rounds: 3
Iterations: 100000

==================================================================================================
Test/Target           MessagePack\Packer  MessagePack\BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------------------------
nil ............................. 0.0164 .................... 0.0266 ...... 0.0068 ........ 0.0061
false ........................... 0.0167 .................... 0.0262 ...... 0.0077 ........ 0.0071
true ............................ 0.0171 .................... 0.0269 ...... 0.0095 ........ 0.0072
7-bit uint #1 ................... 0.0174 .................... 0.0213 ...... 0.0080 ........ 0.0061
7-bit uint #2 ................... 0.0174 .................... 0.0226 ...... 0.0081 ........ 0.0072
7-bit uint #3 ................... 0.0177 .................... 0.0218 ...... 0.0081 ........ 0.0067
5-bit sint #1 ................... 0.0176 .................... 0.0265 ...... 0.0079 ........ 0.0075
5-bit sint #2 ................... 0.0172 .................... 0.0292 ...... 0.0081 ........ 0.0059
5-bit sint #3 ................... 0.0182 .................... 0.0242 ...... 0.0080 ........ 0.0061
8-bit uint #1 ................... 0.0279 .................... 0.0664 ...... 0.0083 ........ 0.0065
8-bit uint #2 ................... 0.0289 .................... 0.0641 ...... 0.0080 ........ 0.0065
8-bit uint #3 ................... 0.0275 .................... 0.0661 ...... 0.0095 ........ 0.0069
16-bit uint #1 .................. 0.0332 .................... 0.0741 ...... 0.0082 ........ 0.0067
16-bit uint #2 .................. 0.0284 .................... 0.0733 ...... 0.0081 ........ 0.0067
16-bit uint #3 .................. 0.0337 .................... 0.0703 ...... 0.0086 ........ 0.0061
32-bit uint #1 .................. 0.0318 .................... 0.0714 ...... 0.0090 ........ 0.0065
32-bit uint #2 .................. 0.0341 .................... 0.0719 ...... 0.0095 ........ 0.0077
32-bit uint #3 .................. 0.0310 .................... 0.0717 ...... 0.0081 ........ 0.0078
64-bit uint #1 .................. 0.0404 .................... 0.0890 ...... 0.0077 ........ 0.0076
64-bit uint #2 .................. 0.0463 .................... 0.0921 ...... 0.0079 ........ 0.0066
8-bit int #1 .................... 0.0290 .................... 0.0687 ...... 0.0082 ........ 0.0077
8-bit int #2 .................... 0.0283 .................... 0.0717 ...... 0.0092 ........ 0.0067
8-bit int #3 .................... 0.0314 .................... 0.0687 ...... 0.0079 ........ 0.0067
16-bit int #1 ................... 0.0295 .................... 0.0842 ...... 0.0096 ........ 0.0066
16-bit int #2 ................... 0.0276 .................... 0.0845 ...... 0.0074 ........ 0.0077
16-bit int #3 ................... 0.0291 .................... 0.0838 ...... 0.0069 ........ 0.0064
32-bit int #1 ................... 0.0309 .................... 0.0828 ...... 0.0085 ........ 0.0082
32-bit int #2 ................... 0.0293 .................... 0.0791 ...... 0.0080 ........ 0.0078
32-bit int #3 ................... 0.0289 .................... 0.0817 ...... 0.0081 ........ 0.0075
64-bit int #1 ................... 0.0399 .................... 0.0868 ...... 0.0071 ........ 0.0071
64-bit int #2 ................... 0.0426 .................... 0.0901 ...... 0.0083 ........ 0.0070
64-bit int #3 ................... 0.0433 .................... 0.0864 ...... 0.0095 ........ 0.0066
64-bit float #1 ................. 0.0375 .................... 0.0751 ...... 0.0081 ........ 0.0077
64-bit float #2 ................. 0.0395 .................... 0.0786 ...... 0.0067 ........ 0.0067
64-bit float #3 ................. 0.0366 .................... 0.0753 ...... 0.0082 ........ 0.0068
fix string #1 ................... 0.0407 .................... 0.0268 ...... 0.0092 ........ 0.0063
fix string #2 ................... 0.0413 .................... 0.0418 ...... 0.0083 ........ 0.0084
fix string #3 ................... 0.0409 .................... 0.0422 ...... 0.0094 ........ 0.0082
fix string #4 ................... 0.0454 .................... 0.0392 ...... 0.0082 ........ 0.0080
8-bit string #1 ................. 0.0459 .................... 0.0944 ...... 0.0099 ........ 0.0080
8-bit string #2 ................. 0.0501 .................... 0.0942 ...... 0.0097 ........ 0.0108
8-bit string #3 ................. 0.0581 .................... 0.0871 ...... 0.0130 ........ 0.0085
16-bit string #1 ................ 0.0584 .................... 0.0970 ...... 0.0132 ........ 0.0088
16-bit string #2 ................ 3.2864 .................... 0.3614 ...... 0.3482 ........ 0.2757
32-bit string ................... 3.2833 .................... 0.3607 ...... 0.3563 ........ 0.2817
wide char string #1 ............. 0.0477 .................... 0.0442 ...... 0.0084 ........ 0.0080
wide char string #2 ............. 0.1062 .................... 0.0903 ...... 0.0083 ........ 0.0079
8-bit binary #1 ...................... I ......................... I ........... F ............. I
8-bit binary #2 ...................... I ......................... I ........... F ............. I
8-bit binary #3 ...................... I ......................... I ........... F ............. I
16-bit binary ........................ I ......................... I ........... F ............. I
32-bit binary ........................ I ......................... I ........... F ............. I
fixext 1 ............................. I ......................... I ........... F ............. F
fixext 2 ............................. I ......................... I ........... F ............. F
fixext 4 ............................. I ......................... I ........... F ............. F
fixext 8 ............................. I ......................... I ........... F ............. F
fixext 16 ............................ I ......................... I ........... F ............. F
8-bit ext ............................ I ......................... I ........... F ............. F
16-bit ext ........................... I ......................... I ........... F ............. F
32-bit ext ........................... I ......................... I ........... F ............. F
fix array #1 .................... 0.0320 .................... 0.0277 ...... 0.0167 ........ 0.0083
fix array #2 .................... 0.1342 .................... 0.1155 ...... 0.0210 ........ 0.0217
16-bit array #1 ................. 0.4031 .................... 0.3732 ...... 0.0431 ........ 0.0512
16-bit array #2 ...................... S ......................... S ........... S ............. S
32-bit array ......................... S ......................... S ........... S ............. S
complex array ........................ I ......................... I ........... F ............. F
fix map #1 ........................... I ......................... I ........... F ............. I
fix map #2 ...................... 0.1022 .................... 0.0886 ...... 0.0191 ........ 0.0181
fix map #3 ........................... I ......................... I ........... F ............. I
fix map #4 ........................... I ......................... I ........... F ............. I
16-bit map #1 ................... 0.7320 .................... 0.6855 ...... 0.0409 ........ 0.0729
16-bit map #2 ........................ S ......................... S ........... S ............. S
32-bit map ........................... S ......................... S ........... S ............. S
complex map ..................... 0.7496 .................... 0.6871 ...... 0.0717 ........ 0.0738
==================================================================================================
Total                            10.2797                      5.5904        1.3019          1.1288
Skipped                                4                           4             4               4
Failed                                 0                           0            17               9
Ignored                               17                          17             0               8
```

> Note, that this is not a fair comparison as the msgpack extension (0.5.2+, 2.0) doesn't
support **ext**, **bin** and utf-8 **str** types.


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
