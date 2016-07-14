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

```sh
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

====================================================================
Test/Target           MessagePack\Packer  MessagePack\BufferUnpacker
--------------------------------------------------------------------
nil ............................. 0.0092 .................... 0.0267
false ........................... 0.0089 .................... 0.0271
true ............................ 0.0083 .................... 0.0248
7-bit uint #1 ................... 0.0091 .................... 0.0204
7-bit uint #2 ................... 0.0113 .................... 0.0208
7-bit uint #3 ................... 0.0108 .................... 0.0206
5-bit sint #1 ................... 0.0097 .................... 0.0247
5-bit sint #2 ................... 0.0119 .................... 0.0253
5-bit sint #3 ................... 0.0101 .................... 0.0239
8-bit uint #1 ................... 0.0132 .................... 0.0431
8-bit uint #2 ................... 0.0143 .................... 0.0468
8-bit uint #3 ................... 0.0146 .................... 0.0471
16-bit uint #1 .................. 0.0173 .................... 0.0557
16-bit uint #2 .................. 0.0199 .................... 0.0565
16-bit uint #3 .................. 0.0184 .................... 0.0568
32-bit uint #1 .................. 0.0211 .................... 0.0699
32-bit uint #2 .................. 0.0234 .................... 0.0695
32-bit uint #3 .................. 0.0232 .................... 0.0737
64-bit uint #1 .................. 0.0348 .................... 0.0839
64-bit uint #2 .................. 0.0357 .................... 0.0804
8-bit int #1 .................... 0.0137 .................... 0.0492
8-bit int #2 .................... 0.0144 .................... 0.0500
8-bit int #3 .................... 0.0142 .................... 0.0534
16-bit int #1 ................... 0.0186 .................... 0.0615
16-bit int #2 ................... 0.0188 .................... 0.0589
16-bit int #3 ................... 0.0173 .................... 0.0602
32-bit int #1 ................... 0.0200 .................... 0.0778
32-bit int #2 ................... 0.0214 .................... 0.0789
32-bit int #3 ................... 0.0205 .................... 0.0765
64-bit int #1 ................... 0.0363 .................... 0.0886
64-bit int #2 ................... 0.0355 .................... 0.0839
64-bit int #3 ................... 0.0328 .................... 0.0849
64-bit float #1 ................. 0.0284 .................... 0.0731
64-bit float #2 ................. 0.0292 .................... 0.0717
64-bit float #3 ................. 0.0317 .................... 0.0727
fix string #1 ................... 0.0238 .................... 0.0245
fix string #2 ................... 0.0265 .................... 0.0427
fix string #3 ................... 0.0253 .................... 0.0436
fix string #4 ................... 0.0270 .................... 0.0431
8-bit string #1 ................. 0.0299 .................... 0.0687
8-bit string #2 ................. 0.0348 .................... 0.0692
8-bit string #3 ................. 0.0426 .................... 0.0701
16-bit string #1 ................ 0.0464 .................... 0.0813
16-bit string #2 ................ 3.2061 .................... 0.3372
32-bit string ................... 3.2552 .................... 0.3571
wide char string #1 ............. 0.0289 .................... 0.0436
wide char string #2 ............. 0.0928 .................... 0.0746
8-bit binary #1 ................. 0.0287 .................... 0.0606
8-bit binary #2 ................. 0.0309 .................... 0.0611
8-bit binary #3 ................. 0.0303 .................... 0.0616
16-bit binary ................... 0.0356 .................... 0.0703
32-bit binary ................... 0.3777 .................... 0.3491
fixext 1 ........................ 0.0246 .................... 0.0909
fixext 2 ........................ 0.0272 .................... 0.0890
fixext 4 ........................ 0.0268 .................... 0.0929
fixext 8 ........................ 0.0272 .................... 0.0925
fixext 16 ....................... 0.0286 .................... 0.0903
8-bit ext ....................... 0.0373 .................... 0.1111
16-bit ext ...................... 0.0408 .................... 0.1166
32-bit ext ...................... 0.3914 .................... 0.3922
fix array #1 .................... 0.0239 .................... 0.0265
fix array #2 .................... 0.0889 .................... 0.1134
16-bit array #1 ................. 0.2424 .................... 0.3572
16-bit array #2 ...................... S ......................... S
32-bit array ......................... S ......................... S
complex array ................... 0.3504 .................... 0.5266
fix map #1 ...................... 0.1637 .................... 0.2390
fix map #2 ...................... 0.0675 .................... 0.0878
fix map #3 ...................... 0.0852 .................... 0.1392
fix map #4 ...................... 0.0763 .................... 0.1125
16-bit map #1 ................... 0.4346 .................... 0.6158
16-bit map #2 ........................ S ......................... S
32-bit map ........................... S ......................... S
complex map ..................... 0.5015 .................... 0.6511
====================================================================
Total                            10.6590                      7.7424
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

```sh
$ MP_BENCH_TARGETS=pure_p,pure_u,pecl_p,pecl_u php tests/bench.php

Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

==================================================================================================
Test/Target           MessagePack\Packer  MessagePack\BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------------------------
nil ............................. 0.0078 .................... 0.0246 ...... 0.0072 ........ 0.0070
false ........................... 0.0103 .................... 0.0237 ...... 0.0077 ........ 0.0056
true ............................ 0.0090 .................... 0.0268 ...... 0.0091 ........ 0.0062
7-bit uint #1 ................... 0.0116 .................... 0.0204 ...... 0.0084 ........ 0.0056
7-bit uint #2 ................... 0.0090 .................... 0.0219 ...... 0.0080 ........ 0.0059
7-bit uint #3 ................... 0.0102 .................... 0.0190 ...... 0.0081 ........ 0.0055
5-bit sint #1 ................... 0.0099 .................... 0.0243 ...... 0.0082 ........ 0.0061
5-bit sint #2 ................... 0.0099 .................... 0.0242 ...... 0.0077 ........ 0.0060
5-bit sint #3 ................... 0.0101 .................... 0.0247 ...... 0.0081 ........ 0.0054
8-bit uint #1 ................... 0.0148 .................... 0.0445 ...... 0.0070 ........ 0.0063
8-bit uint #2 ................... 0.0136 .................... 0.0459 ...... 0.0100 ........ 0.0076
8-bit uint #3 ................... 0.0148 .................... 0.0486 ...... 0.0070 ........ 0.0081
16-bit uint #1 .................. 0.0172 .................... 0.0533 ...... 0.0092 ........ 0.0062
16-bit uint #2 .................. 0.0183 .................... 0.0552 ...... 0.0081 ........ 0.0064
16-bit uint #3 .................. 0.0193 .................... 0.0537 ...... 0.0091 ........ 0.0060
32-bit uint #1 .................. 0.0230 .................... 0.0695 ...... 0.0083 ........ 0.0076
32-bit uint #2 .................. 0.0209 .................... 0.0713 ...... 0.0092 ........ 0.0059
32-bit uint #3 .................. 0.0218 .................... 0.0672 ...... 0.0082 ........ 0.0064
64-bit uint #1 .................. 0.0331 .................... 0.0808 ...... 0.0081 ........ 0.0065
64-bit uint #2 .................. 0.0335 .................... 0.0858 ...... 0.0080 ........ 0.0075
8-bit int #1 .................... 0.0122 .................... 0.0516 ...... 0.0083 ........ 0.0077
8-bit int #2 .................... 0.0137 .................... 0.0499 ...... 0.0090 ........ 0.0074
8-bit int #3 .................... 0.0121 .................... 0.0502 ...... 0.0080 ........ 0.0064
16-bit int #1 ................... 0.0172 .................... 0.0567 ...... 0.0082 ........ 0.0065
16-bit int #2 ................... 0.0174 .................... 0.0601 ...... 0.0089 ........ 0.0063
16-bit int #3 ................... 0.0185 .................... 0.0599 ...... 0.0076 ........ 0.0089
32-bit int #1 ................... 0.0211 .................... 0.0765 ...... 0.0083 ........ 0.0086
32-bit int #2 ................... 0.0214 .................... 0.0804 ...... 0.0090 ........ 0.0064
32-bit int #3 ................... 0.0209 .................... 0.0773 ...... 0.0081 ........ 0.0066
64-bit int #1 ................... 0.0337 .................... 0.0877 ...... 0.0099 ........ 0.0064
64-bit int #2 ................... 0.0362 .................... 0.0888 ...... 0.0080 ........ 0.0062
64-bit int #3 ................... 0.0329 .................... 0.0879 ...... 0.0097 ........ 0.0063
64-bit float #1 ................. 0.0289 .................... 0.0726 ...... 0.0091 ........ 0.0072
64-bit float #2 ................. 0.0284 .................... 0.0726 ...... 0.0083 ........ 0.0069
64-bit float #3 ................. 0.0333 .................... 0.0751 ...... 0.0080 ........ 0.0064
fix string #1 ................... 0.0240 .................... 0.0268 ...... 0.0083 ........ 0.0069
fix string #2 ................... 0.0260 .................... 0.0415 ...... 0.0095 ........ 0.0081
fix string #3 ................... 0.0253 .................... 0.0425 ...... 0.0114 ........ 0.0092
fix string #4 ................... 0.0269 .................... 0.0404 ...... 0.0084 ........ 0.0077
8-bit string #1 ................. 0.0318 .................... 0.0658 ...... 0.0078 ........ 0.0072
8-bit string #2 ................. 0.0369 .................... 0.0693 ...... 0.0086 ........ 0.0079
8-bit string #3 ................. 0.0427 .................... 0.0713 ...... 0.0141 ........ 0.0089
16-bit string #1 ................ 0.0472 .................... 0.0799 ...... 0.0130 ........ 0.0104
16-bit string #2 ................ 3.2651 .................... 0.3395 ...... 0.3499 ........ 0.2647
32-bit string ................... 3.2639 .................... 0.3556 ...... 0.3450 ........ 0.2709
wide char string #1 ............. 0.0326 .................... 0.0436 ...... 0.0083 ........ 0.0077
wide char string #2 ............. 0.0886 .................... 0.0728 ...... 0.0081 ........ 0.0078
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
fix array #1 .................... 0.0239 .................... 0.0284 ...... 0.0169 ........ 0.0096
fix array #2 .................... 0.0807 .................... 0.1063 ...... 0.0224 ........ 0.0210
16-bit array #1 ................. 0.2406 .................... 0.3337 ...... 0.0407 ........ 0.0504
16-bit array #2 ...................... S ......................... S ........... S ............. S
32-bit array ......................... S ......................... S ........... S ............. S
complex array ........................ I ......................... I ........... F ............. F
fix map #1 ........................... I ......................... I ........... F ............. I
fix map #2 ...................... 0.0692 .................... 0.0855 ...... 0.0198 ........ 0.0188
fix map #3 ........................... I ......................... I ........... F ............. I
fix map #4 ........................... I ......................... I ........... F ............. I
16-bit map #1 ................... 0.4437 .................... 0.5810 ...... 0.0421 ........ 0.0677
16-bit map #2 ........................ S ......................... S ........... S ............. S
32-bit map ........................... S ......................... S ........... S ............. S
complex map ..................... 0.5008 .................... 0.6394 ...... 0.0723 ........ 0.0780
==================================================================================================
Total                             8.9357                      4.9563        1.2997          1.0949
Skipped                                4                           4             4               4
Failed                                 0                           0            17               9
Ignored                               17                          17             0               8
```

> Note, that this is not a fair comparison as the msgpack extension (0.5.2+, 2.0) doesn't
support **ext**, **bin** and utf-8 **str** types.


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
