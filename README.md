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
Filter: MessagePack\Tests\Perf\Filter\NameFilter
Rounds: 3
Iterations: 100000

====================================================================
Test/Target           MessagePack\Packer  MessagePack\BufferUnpacker
--------------------------------------------------------------------
nil ............................. 0.0135 .................... 0.0242
false ........................... 0.0151 .................... 0.0276
true ............................ 0.0151 .................... 0.0256
7-bit uint #1 ................... 0.0169 .................... 0.0199
7-bit uint #2 ................... 0.0186 .................... 0.0188
7-bit uint #3 ................... 0.0185 .................... 0.0232
5-bit sint #1 ................... 0.0208 .................... 0.0252
5-bit sint #2 ................... 0.0178 .................... 0.0242
5-bit sint #3 ................... 0.0191 .................... 0.0242
8-bit uint #1 ................... 0.0299 .................... 0.0479
8-bit uint #2 ................... 0.0280 .................... 0.0449
8-bit uint #3 ................... 0.0275 .................... 0.0486
16-bit uint #1 .................. 0.0321 .................... 0.0537
16-bit uint #2 .................. 0.0297 .................... 0.0544
16-bit uint #3 .................. 0.0309 .................... 0.0550
32-bit uint #1 .................. 0.0289 .................... 0.0720
32-bit uint #2 .................. 0.0296 .................... 0.0750
32-bit uint #3 .................. 0.0293 .................... 0.0751
64-bit uint #1 .................. 0.0450 .................... 0.0870
64-bit uint #2 .................. 0.0426 .................... 0.0914
8-bit int #1 .................... 0.0274 .................... 0.0509
8-bit int #2 .................... 0.0299 .................... 0.0511
8-bit int #3 .................... 0.0294 .................... 0.0518
16-bit int #1 ................... 0.0317 .................... 0.0618
16-bit int #2 ................... 0.0284 .................... 0.0613
16-bit int #3 ................... 0.0281 .................... 0.0567
32-bit int #1 ................... 0.0316 .................... 0.0811
32-bit int #2 ................... 0.0295 .................... 0.0815
32-bit int #3 ................... 0.0314 .................... 0.0767
64-bit int #1 ................... 0.0415 .................... 0.0886
64-bit int #2 ................... 0.0422 .................... 0.0884
64-bit int #3 ................... 0.0414 .................... 0.0933
64-bit float #1 ................. 0.0354 .................... 0.0748
64-bit float #2 ................. 0.0354 .................... 0.0744
64-bit float #3 ................. 0.0374 .................... 0.0748
fix string #1 ................... 0.0325 .................... 0.0254
fix string #2 ................... 0.0326 .................... 0.0411
fix string #3 ................... 0.0320 .................... 0.0403
fix string #4 ................... 0.0361 .................... 0.0427
8-bit string #1 ................. 0.0456 .................... 0.0684
8-bit string #2 ................. 0.0534 .................... 0.0682
8-bit string #3 ................. 0.0598 .................... 0.0739
16-bit string #1 ................ 0.0581 .................... 0.0833
16-bit string #2 ................ 3.2417 .................... 0.3393
32-bit string ................... 3.2793 .................... 0.3664
wide char string #1 ............. 0.0414 .................... 0.0404
wide char string #2 ............. 0.1068 .................... 0.0701
8-bit binary #1 ................. 0.0438 .................... 0.0641
8-bit binary #2 ................. 0.0451 .................... 0.0624
8-bit binary #3 ................. 0.0443 .................... 0.0610
16-bit binary ................... 0.0458 .................... 0.0772
32-bit binary ................... 0.3977 .................... 0.3500
fixext 1 ........................ 0.0423 .................... 0.0868
fixext 2 ........................ 0.0429 .................... 0.0899
fixext 4 ........................ 0.0443 .................... 0.0918
fixext 8 ........................ 0.0453 .................... 0.0955
fixext 16 ....................... 0.0475 .................... 0.0908
8-bit ext ....................... 0.0493 .................... 0.1097
16-bit ext ...................... 0.0510 .................... 0.1194
32-bit ext ...................... 0.3958 .................... 0.4024
fix array #1 .................... 0.0306 .................... 0.0272
fix array #2 .................... 0.1184 .................... 0.1153
16-bit array #1 ................. 0.4040 .................... 0.3510
16-bit array #2 ...................... S ......................... S
32-bit array ......................... S ......................... S
complex array ................... 0.5205 .................... 0.5579
fix map #1 ...................... 0.2467 .................... 0.2446
fix map #2 ...................... 0.0910 .................... 0.0914
fix map #3 ...................... 0.1193 .................... 0.1440
fix map #4 ...................... 0.1214 .................... 0.1128
16-bit map #1 ................... 0.7065 .................... 0.6257
16-bit map #2 ........................ S ......................... S
32-bit map ........................... S ......................... S
complex map ..................... 0.6907 .................... 0.6785
====================================================================
Total                            12.3730                      7.8941
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
nil ............................. 0.0146 .................... 0.0282 ...... 0.0066 ........ 0.0064
false ........................... 0.0171 .................... 0.0252 ...... 0.0092 ........ 0.0073
true ............................ 0.0163 .................... 0.0304 ...... 0.0086 ........ 0.0068
7-bit uint #1 ................... 0.0209 .................... 0.0214 ...... 0.0078 ........ 0.0066
7-bit uint #2 ................... 0.0166 .................... 0.0229 ...... 0.0073 ........ 0.0061
7-bit uint #3 ................... 0.0188 .................... 0.0216 ...... 0.0080 ........ 0.0058
5-bit sint #1 ................... 0.0209 .................... 0.0270 ...... 0.0077 ........ 0.0061
5-bit sint #2 ................... 0.0193 .................... 0.0252 ...... 0.0078 ........ 0.0074
5-bit sint #3 ................... 0.0184 .................... 0.0244 ...... 0.0077 ........ 0.0061
8-bit uint #1 ................... 0.0268 .................... 0.0469 ...... 0.0107 ........ 0.0066
8-bit uint #2 ................... 0.0289 .................... 0.0447 ...... 0.0090 ........ 0.0065
8-bit uint #3 ................... 0.0292 .................... 0.0458 ...... 0.0071 ........ 0.0064
16-bit uint #1 .................. 0.0298 .................... 0.0512 ...... 0.0088 ........ 0.0079
16-bit uint #2 .................. 0.0296 .................... 0.0542 ...... 0.0080 ........ 0.0066
16-bit uint #3 .................. 0.0309 .................... 0.0523 ...... 0.0093 ........ 0.0074
32-bit uint #1 .................. 0.0289 .................... 0.0723 ...... 0.0087 ........ 0.0067
32-bit uint #2 .................. 0.0333 .................... 0.0706 ...... 0.0078 ........ 0.0064
32-bit uint #3 .................. 0.0288 .................... 0.0743 ...... 0.0079 ........ 0.0066
64-bit uint #1 .................. 0.0423 .................... 0.0851 ...... 0.0089 ........ 0.0067
64-bit uint #2 .................. 0.0408 .................... 0.0882 ...... 0.0091 ........ 0.0076
8-bit int #1 .................... 0.0292 .................... 0.0521 ...... 0.0075 ........ 0.0072
8-bit int #2 .................... 0.0274 .................... 0.0507 ...... 0.0079 ........ 0.0067
8-bit int #3 .................... 0.0279 .................... 0.0526 ...... 0.0071 ........ 0.0064
16-bit int #1 ................... 0.0280 .................... 0.0586 ...... 0.0085 ........ 0.0081
16-bit int #2 ................... 0.0317 .................... 0.0590 ...... 0.0084 ........ 0.0081
16-bit int #3 ................... 0.0286 .................... 0.0621 ...... 0.0075 ........ 0.0066
32-bit int #1 ................... 0.0288 .................... 0.0830 ...... 0.0081 ........ 0.0077
32-bit int #2 ................... 0.0292 .................... 0.0806 ...... 0.0094 ........ 0.0066
32-bit int #3 ................... 0.0304 .................... 0.0859 ...... 0.0080 ........ 0.0065
64-bit int #1 ................... 0.0418 .................... 0.0876 ...... 0.0082 ........ 0.0068
64-bit int #2 ................... 0.0425 .................... 0.0889 ...... 0.0085 ........ 0.0070
64-bit int #3 ................... 0.0447 .................... 0.0917 ...... 0.0084 ........ 0.0066
64-bit float #1 ................. 0.0392 .................... 0.0832 ...... 0.0090 ........ 0.0062
64-bit float #2 ................. 0.0353 .................... 0.0754 ...... 0.0074 ........ 0.0092
64-bit float #3 ................. 0.0368 .................... 0.0768 ...... 0.0073 ........ 0.0064
fix string #1 ................... 0.0310 .................... 0.0278 ...... 0.0076 ........ 0.0075
fix string #2 ................... 0.0351 .................... 0.0413 ...... 0.0079 ........ 0.0080
fix string #3 ................... 0.0316 .................... 0.0435 ...... 0.0083 ........ 0.0085
fix string #4 ................... 0.0366 .................... 0.0401 ...... 0.0082 ........ 0.0079
8-bit string #1 ................. 0.0452 .................... 0.0722 ...... 0.0083 ........ 0.0078
8-bit string #2 ................. 0.0503 .................... 0.0673 ...... 0.0083 ........ 0.0074
8-bit string #3 ................. 0.0575 .................... 0.0683 ...... 0.0133 ........ 0.0095
16-bit string #1 ................ 0.0563 .................... 0.0779 ...... 0.0123 ........ 0.0087
16-bit string #2 ................ 3.2773 .................... 0.3390 ...... 0.3482 ........ 0.2674
32-bit string ................... 3.2505 .................... 0.3546 ...... 0.3409 ........ 0.2704
wide char string #1 ............. 0.0423 .................... 0.0434 ...... 0.0082 ........ 0.0069
wide char string #2 ............. 0.1040 .................... 0.0689 ...... 0.0085 ........ 0.0079
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
fix array #1 .................... 0.0318 .................... 0.0273 ...... 0.0172 ........ 0.0082
fix array #2 .................... 0.1204 .................... 0.1175 ...... 0.0226 ........ 0.0216
16-bit array #1 ................. 0.3913 .................... 0.3492 ...... 0.0374 ........ 0.0490
16-bit array #2 ...................... S ......................... S ........... S ............. S
32-bit array ......................... S ......................... S ........... S ............. S
complex array ........................ I ......................... I ........... F ............. F
fix map #1 ........................... I ......................... I ........... F ............. I
fix map #2 ...................... 0.0940 .................... 0.0859 ...... 0.0183 ........ 0.0225
fix map #3 ........................... I ......................... I ........... F ............. I
fix map #4 ........................... I ......................... I ........... F ............. I
16-bit map #1 ................... 0.7112 .................... 0.6340 ...... 0.0404 ........ 0.0708
16-bit map #2 ........................ S ......................... S ........... S ............. S
32-bit map ........................... S ......................... S ........... S ............. S
complex map ..................... 0.6881 .................... 0.6817 ...... 0.0692 ........ 0.0760
==================================================================================================
Total                            10.0680                      5.1397        1.2722          1.1059
Skipped                                4                           4             4               4
Failed                                 0                           0            17               9
Ignored                               17                          17             0               8
```

> Note, that this is not a fair comparison as the msgpack extension (0.5.2+, 2.0) doesn't
support **ext**, **bin** and utf-8 **str** types.


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
