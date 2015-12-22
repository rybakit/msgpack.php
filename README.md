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
$mpMap1 = $packer->pack([0 => 1, 3 => 3]);         // MP map {0: 1, 3: 3}
$mpMap2 = $packer->pack([1 => 1, 2 => 2]);         // MP map {1: 1, 2: 2}
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
$packer->packMap([1, 2]);             // MP pap
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
is no more than a tiny wrapper around `BufferUnpacker` with a single method `unpack()`:

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

$packed = $packer->pack(['foo' => new \DateTime(), 'bar' => 'baz']);
$raw = $unpacker->reset($packed)->unpack());
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
$ php tests/bench.php

Filter: MessagePack\Tests\Perf\Filter\NameFilter
Cycles: 3
Iterations: 100000

====================================================================
Test/Target           MessagePack\Packer  MessagePack\BufferUnpacker
--------------------------------------------------------------------
nil ............................. 0.0146 .................... 0.0336
false ........................... 0.0150 .................... 0.0360
true ............................ 0.0168 .................... 0.0316
7-bit uint #1 ................... 0.0187 .................... 0.0239
7-bit uint #2 ................... 0.0200 .................... 0.0247
7-bit uint #3 ................... 0.0202 .................... 0.0243
5-bit sint #1 ................... 0.0216 .................... 0.0344
5-bit sint #2 ................... 0.0190 .................... 0.0316
5-bit sint #3 ................... 0.0189 .................... 0.0343
8-bit uint #1 ................... 0.0298 .................... 0.0725
8-bit uint #2 ................... 0.0296 .................... 0.0714
8-bit uint #3 ................... 0.0290 .................... 0.0719
16-bit uint #1 .................. 0.0297 .................... 0.0794
16-bit uint #2 .................. 0.0297 .................... 0.0772
16-bit uint #3 .................. 0.0307 .................... 0.0774
32-bit uint #1 .................. 0.0310 .................... 0.0775
32-bit uint #2 .................. 0.0300 .................... 0.0811
32-bit uint #3 .................. 0.0321 .................... 0.0787
64-bit uint #1 .................. 0.0401 .................... 0.0956
64-bit uint #2 .................. 0.0405 .................... 0.0928
8-bit int #1 .................... 0.0310 .................... 0.0755
8-bit int #2 .................... 0.0274 .................... 0.0727
8-bit int #3 .................... 0.0278 .................... 0.0768
16-bit int #1 ................... 0.0290 .................... 0.0848
16-bit int #2 ................... 0.0286 .................... 0.0872
16-bit int #3 ................... 0.0288 .................... 0.0895
32-bit int #1 ................... 0.0292 .................... 0.0935
32-bit int #2 ................... 0.0304 .................... 0.0891
32-bit int #3 ................... 0.0293 .................... 0.0882
64-bit int #1 ................... 0.0451 .................... 0.0958
64-bit int #2 ................... 0.0407 .................... 0.0983
64-bit int #3 ................... 0.0426 .................... 0.0941
64-bit float #1 ................. 0.0358 .................... 0.0832
64-bit float #2 ................. 0.0365 .................... 0.0831
64-bit float #3 ................. 0.0360 .................... 0.0804
fix string #1 ................... 0.0396 .................... 0.0310
fix string #2 ................... 0.0368 .................... 0.0485
fix string #3 ................... 0.0369 .................... 0.0507
fix string #4 ................... 0.0410 .................... 0.0500
8-bit string #1 ................. 0.0529 .................... 0.1013
8-bit string #2 ................. 0.0568 .................... 0.1037
8-bit string #3 ................. 0.0700 .................... 0.1013
16-bit string #1 ................ 0.0705 .................... 0.1049
16-bit string #2 ................ 5.5168 .................... 0.3627
32-bit string ................... 5.5578 .................... 0.3806
wide char string #1 ............. 0.0449 .................... 0.0487
wide char string #2 ............. 0.0528 .................... 0.1021
8-bit binary #1 ................. 0.0421 .................... 0.0915
8-bit binary #2 ................. 0.0414 .................... 0.0955
8-bit binary #3 ................. 0.0444 .................... 0.0913
16-bit binary ................... 0.0415 .................... 0.0993
32-bit binary ................... 0.3858 .................... 0.3640
fixext 1 ........................ 0.0446 .................... 0.1176
fixext 2 ........................ 0.0430 .................... 0.1192
fixext 4 ........................ 0.0432 .................... 0.1194
fixext 8 ........................ 0.0491 .................... 0.1131
fixext 16 ....................... 0.0446 .................... 0.1198
8-bit ext ....................... 0.0541 .................... 0.1543
16-bit ext ...................... 0.0529 .................... 0.1713
32-bit ext ...................... 0.4053 .................... 0.4323
fix array #1 .................... 0.0342 .................... 0.0346
fix array #2 .................... 0.1301 .................... 0.1330
16-bit array #1 ................. 0.4228 .................... 0.4189
16-bit array #2 ...................... S ......................... S
32-bit array ......................... S ......................... S
complex array ................... 0.5417 .................... 0.6958
fix map #1 ...................... 0.2586 .................... 0.3144
fix map #2 ...................... 0.0979 .................... 0.1049
fix map #3 ...................... 0.1182 .................... 0.2156
16-bit map #1 ................... 0.7512 .................... 0.7400
16-bit map #2 ........................ S ......................... S
32-bit map ........................... S ......................... S
complex map ..................... 0.7420 .................... 0.7913
====================================================================
Total                            16.9806                      9.4647
Skipped                                4                           4
Failed                                 0                           0
Ignored                                0                           0
```

You may change default benchmark settings by defining the following environment variables:

 * `MP_BENCH_TARGETS` (pure_p, pure_u, pecl_p, pecl_u)
 * `MP_BENCH_SIZE`/`MP_BENCH_TIME`
 * `MP_BENCH_CYCLES`
 * `MP_BENCH_TESTS`

For example:

```sh
$ export MP_BENCH_TARGET=pure_p
$ export MP_BENCH_SIZE=1000000
$ export MP_BENCH_CYCLES=2
$ export MP_BENCH_TESTS='complex array, complex map'
$ php tests/bench.php
```

Another example, benchmarking both the library and [msgpack pecl extension](https://pecl.php.net/package/msgpack):

```sh
$ MP_BENCH_TARGETS=pure_p,pure_u,pecl_p,pecl_u php tests/bench.php

Filter: MessagePack\Tests\Perf\Filter\NameFilter
Cycles: 3
Iterations: 100000

==================================================================================================
Test/Target           MessagePack\Packer  MessagePack\BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------------------------
nil ............................. 0.0150 .................... 0.0324 ...... 0.0083 ........ 0.0075
false ........................... 0.0158 .................... 0.0358 ...... 0.0093 ........ 0.0068
true ............................ 0.0173 .................... 0.0352 ...... 0.0091 ........ 0.0067
7-bit uint #1 ................... 0.0178 .................... 0.0254 ...... 0.0107 ........ 0.0063
7-bit uint #2 ................... 0.0187 .................... 0.0242 ...... 0.0091 ........ 0.0081
7-bit uint #3 ................... 0.0198 .................... 0.0245 ...... 0.0089 ........ 0.0066
5-bit sint #1 ................... 0.0195 .................... 0.0314 ...... 0.0090 ........ 0.0066
5-bit sint #2 ................... 0.0185 .................... 0.0328 ...... 0.0088 ........ 0.0070
5-bit sint #3 ................... 0.0192 .................... 0.0318 ...... 0.0086 ........ 0.0064
8-bit uint #1 ................... 0.0285 .................... 0.0734 ...... 0.0087 ........ 0.0078
8-bit uint #2 ................... 0.0286 .................... 0.0735 ...... 0.0086 ........ 0.0084
8-bit uint #3 ................... 0.0291 .................... 0.0723 ...... 0.0073 ........ 0.0067
16-bit uint #1 .................. 0.0302 .................... 0.0781 ...... 0.0098 ........ 0.0076
16-bit uint #2 .................. 0.0303 .................... 0.0813 ...... 0.0089 ........ 0.0080
16-bit uint #3 .................. 0.0299 .................... 0.0797 ...... 0.0090 ........ 0.0064
32-bit uint #1 .................. 0.0296 .................... 0.0817 ...... 0.0096 ........ 0.0070
32-bit uint #2 .................. 0.0301 .................... 0.0797 ...... 0.0089 ........ 0.0068
32-bit uint #3 .................. 0.0316 .................... 0.0818 ...... 0.0097 ........ 0.0070
64-bit uint #1 .................. 0.0436 .................... 0.0970 ...... 0.0089 ........ 0.0069
64-bit uint #2 .................. 0.0431 .................... 0.0930 ...... 0.0097 ........ 0.0075
8-bit int #1 .................... 0.0329 .................... 0.0752 ...... 0.0096 ........ 0.0071
8-bit int #2 .................... 0.0309 .................... 0.0733 ...... 0.0094 ........ 0.0069
8-bit int #3 .................... 0.0284 .................... 0.0740 ...... 0.0087 ........ 0.0076
16-bit int #1 ................... 0.0314 .................... 0.0909 ...... 0.0093 ........ 0.0077
16-bit int #2 ................... 0.0318 .................... 0.0925 ...... 0.0080 ........ 0.0068
16-bit int #3 ................... 0.0305 .................... 0.0872 ...... 0.0099 ........ 0.0069
32-bit int #1 ................... 0.0323 .................... 0.0864 ...... 0.0109 ........ 0.0069
32-bit int #2 ................... 0.0304 .................... 0.0874 ...... 0.0097 ........ 0.0083
32-bit int #3 ................... 0.0322 .................... 0.0900 ...... 0.0089 ........ 0.0075
64-bit int #1 ................... 0.0452 .................... 0.0953 ...... 0.0102 ........ 0.0069
64-bit int #2 ................... 0.0439 .................... 0.0990 ...... 0.0098 ........ 0.0071
64-bit int #3 ................... 0.0455 .................... 0.1066 ...... 0.0095 ........ 0.0083
64-bit float #1 ................. 0.0381 .................... 0.0914 ...... 0.0108 ........ 0.0088
64-bit float #2 ................. 0.0410 .................... 0.0905 ...... 0.0087 ........ 0.0078
64-bit float #3 ................. 0.0401 .................... 0.0884 ...... 0.0092 ........ 0.0081
fix string #1 ................... 0.0384 .................... 0.0378 ...... 0.0106 ........ 0.0076
fix string #2 ................... 0.0394 .................... 0.0565 ...... 0.0104 ........ 0.0104
fix string #3 ................... 0.0362 .................... 0.0492 ...... 0.0107 ........ 0.0090
fix string #4 ................... 0.0431 .................... 0.0511 ...... 0.0092 ........ 0.0096
8-bit string #1 ................. 0.0532 .................... 0.1021 ...... 0.0103 ........ 0.0092
8-bit string #2 ................. 0.0603 .................... 0.1008 ...... 0.0098 ........ 0.0082
8-bit string #3 ................. 0.0725 .................... 0.0972 ...... 0.0146 ........ 0.0113
16-bit string #1 ................ 0.0677 .................... 0.1063 ...... 0.0136 ........ 0.0102
16-bit string #2 ................ 5.5084 .................... 0.3627 ...... 0.3453 ........ 0.2780
32-bit string ................... 5.6834 .................... 0.3985 ...... 0.3581 ........ 0.2897
wide char string #1 ............. 0.0388 .................... 0.0494 ...... 0.0091 ........ 0.0090
wide char string #2 ............. 0.0531 .................... 0.1037 ...... 0.0121 ........ 0.0089
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
fix array #1 .................... 0.0360 .................... 0.0375 ...... 0.0201 ........ 0.0084
fix array #2 .................... 0.1346 .................... 0.1383 ...... 0.0241 ........ 0.0232
16-bit array #1 ................. 0.4201 .................... 0.4261 ...... 0.0481 ........ 0.0504
16-bit array #2 ...................... S ......................... S ........... S ............. S
32-bit array ......................... S ......................... S ........... S ............. S
complex array ........................ I ......................... I ........... F ............. F
fix map #1 ........................... I ......................... I ........... F ............. I
fix map #2 ...................... 0.0983 .................... 0.1033 ...... 0.0197 ........ 0.0205
fix map #3 ........................... I ......................... I ........... F ............. I
16-bit map #1 ................... 0.7686 .................... 0.7651 ...... 0.0472 ........ 0.0707
16-bit map #2 ........................ S ......................... S ........... S ............. S
32-bit map ........................... S ......................... S ........... S ............. S
complex map ..................... 0.7609 .................... 0.8064 ...... 0.0741 ........ 0.0830
==================================================================================================
Total                            14.9638                      6.2847        1.3706          1.1717
Skipped                                4                           4             4               4
Failed                                 0                           0            16               9
Ignored                               16                          16             0               7
```

> Note, that this is not a fair comparison as the msgpack extension (5.2+, 2.0) doesn't
support **ext**, **bin** and utf-8 **str** types.


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
