# msgpack.php

A pure PHP implementation of the MessagePack serialization format.

[![Build Status](https://travis-ci.org/rybakit/msgpack.php.svg?branch=master)](https://travis-ci.org/rybakit/msgpack.php)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)


## Features

 * Fully compliant with the latest [MessagePack specification](https://github.com/msgpack/msgpack/blob/master/spec.md),
   including bin, str and ext types
 * Supports [streaming unpacking](#unpacking)
 * Supports [unsigned 64-bit integers handling](#unsigned-64-bit-integers)
 * Supports [object serialization](#custom-types)
 * Works with PHP 5.4-7.0 and HHVM
 * Fully tested
 * [Relatively fast](#performance)


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
$mpMap1 = $packer->pack([0 => 1, 3 => 3]);         // MP map {0: 1, 3:3}
$mpMap2 = $packer->pack([1 => 1, 2 => 2]);         // MP map {1: 1, 2:2}
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
$packer->packArr([1, 2]);             // MP array
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
$unpacker->setBigIntMode(BufferUnpacker::BIGINT_AS_STRING);
```

Or to a `Gmp` number (make sure that [gmp](http://php.net/manual/en/book.gmp.php) extension is enabled):

```php
$unpacker->setBigIntMode(BufferUnpacker::BIGINT_AS_GMP);
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

You may also change default settings by defining the following environment variables:

 * `MP_BENCH_TARGET` (pure_p, pure_u, pecl_p, pecl_u)
 * `MP_BENCH_SIZE`
 * `MP_BENCH_CYCLES`
 * `MP_BENCH_TESTS`

For example:

```sh
$ export MP_BENCH_TARGET=pure_p
$ export MP_BENCH_SIZE=1000000
$ export MP_BENCH_CYCLES=1
$ export MP_BENCH_TESTS='complex array'
$ php tests/bench.php
```


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
