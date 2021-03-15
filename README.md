# msgpack.php

[![Quality Assurance](https://github.com/rybakit/msgpack.php/workflows/QA/badge.svg)](https://github.com/rybakit/msgpack.php/actions?query=workflow%3AQA)
[![Code Coverage](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)
[![Mentioned in Awesome PHP](https://awesome.re/mentioned-badge.svg)](https://github.com/ziadoz/awesome-php#data-structure-and-storage)

A pure PHP implementation of the [MessagePack](https://msgpack.org/) serialization format.


## Features

 * Fully compliant with the latest [MessagePack specification](https://github.com/msgpack/msgpack/blob/master/spec.md),
   including **bin**, **str** and **ext** types
 * Supports [streaming unpacking](#unpacking)
 * Supports [unsigned 64-bit integers handling](#unpacking-options)
 * Supports [object serialization](#type-transformers)
 * [Fully tested](https://github.com/rybakit/msgpack.php/actions?query=workflow%3AQA)
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
$packer->packFloat(M_PI);     // MP float (32 or 64)
$packer->packFloat32(M_PI);   // MP float 32
$packer->packFloat64(M_PI);   // MP float 64
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

        return $packer->packExt($this->type, $value->format('Y-m-d\TH:i:s.uP'));
    }

    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        return new \DateTimeImmutable($unpacker->read(32));
    }
}
```

Register `DateTimeExtension` for both the packer and the unpacker with a unique extension type 
(an integer from 0 to 127) and you're ready to go:

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

The command above will create a container named `msgpack` with PHP 8.0 runtime.
You may change the default runtime by defining the `PHP_IMAGE` environment variable:

```sh
PHP_IMAGE='php:7.4-cli' ./dockerfile.sh | docker build -t msgpack -
```

> *See a list of various images [here](https://hub.docker.com/_/php).*

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
php -n -dzend_extension=opcache.so \
-dpcre.jit=1 -dopcache.enable=1 -dopcache.enable_cli=1 \
tests/bench.php
```

<details>
<summary><strong>Example output</strong></summary>

```
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

=============================================
Test/Target            Packer  BufferUnpacker
---------------------------------------------
nil .................. 0.0028 ........ 0.0156
false ................ 0.0027 ........ 0.0135
true ................. 0.0031 ........ 0.0138
7-bit uint #1 ........ 0.0062 ........ 0.0143
7-bit uint #2 ........ 0.0064 ........ 0.0125
7-bit uint #3 ........ 0.0060 ........ 0.0127
5-bit sint #1 ........ 0.0068 ........ 0.0146
5-bit sint #2 ........ 0.0084 ........ 0.0135
5-bit sint #3 ........ 0.0068 ........ 0.0137
8-bit uint #1 ........ 0.0086 ........ 0.0212
8-bit uint #2 ........ 0.0091 ........ 0.0209
8-bit uint #3 ........ 0.0087 ........ 0.0212
16-bit uint #1 ....... 0.0121 ........ 0.0242
16-bit uint #2 ....... 0.0129 ........ 0.0241
16-bit uint #3 ....... 0.0133 ........ 0.0247
32-bit uint #1 ....... 0.0149 ........ 0.0337
32-bit uint #2 ....... 0.0116 ........ 0.0331
32-bit uint #3 ....... 0.0133 ........ 0.0324
64-bit uint #1 ....... 0.0137 ........ 0.0309
64-bit uint #2 ....... 0.0139 ........ 0.0313
64-bit uint #3 ....... 0.0152 ........ 0.0310
8-bit int #1 ......... 0.0090 ........ 0.0241
8-bit int #2 ......... 0.0087 ........ 0.0224
8-bit int #3 ......... 0.0092 ........ 0.0236
16-bit int #1 ........ 0.0129 ........ 0.0255
16-bit int #2 ........ 0.0131 ........ 0.0255
16-bit int #3 ........ 0.0137 ........ 0.0252
32-bit int #1 ........ 0.0125 ........ 0.0337
32-bit int #2 ........ 0.0125 ........ 0.0340
32-bit int #3 ........ 0.0152 ........ 0.0334
64-bit int #1 ........ 0.0140 ........ 0.0309
64-bit int #2 ........ 0.0163 ........ 0.0338
64-bit int #3 ........ 0.0139 ........ 0.0306
64-bit int #4 ........ 0.0135 ........ 0.0315
64-bit float #1 ...... 0.0153 ........ 0.0307
64-bit float #2 ...... 0.0150 ........ 0.0301
64-bit float #3 ...... 0.0146 ........ 0.0305
fix string #1 ........ 0.0051 ........ 0.0134
fix string #2 ........ 0.0191 ........ 0.0229
fix string #3 ........ 0.0218 ........ 0.0237
fix string #4 ........ 0.0222 ........ 0.0253
8-bit string #1 ...... 0.0244 ........ 0.0309
8-bit string #2 ...... 0.0256 ........ 0.0311
8-bit string #3 ...... 0.0309 ........ 0.0328
16-bit string #1 ..... 0.0333 ........ 0.0382
16-bit string #2 ..... 1.9079 ........ 0.1739
32-bit string ........ 1.8997 ........ 0.1805
wide char string #1 .. 0.0205 ........ 0.0235
wide char string #2 .. 0.0240 ........ 0.0299
8-bit binary #1 ...... 0.0201 ........ 0.0294
8-bit binary #2 ...... 0.0229 ........ 0.0306
8-bit binary #3 ...... 0.0200 ........ 0.0314
16-bit binary ........ 0.0236 ........ 0.0352
32-bit binary ........ 0.1669 ........ 0.1818
fix array #1 ......... 0.0055 ........ 0.0156
fix array #2 ......... 0.0300 ........ 0.0341
fix array #3 ......... 0.0539 ........ 0.0527
16-bit array #1 ...... 0.1410 ........ 0.1639
16-bit array #2 ........... S ............. S
32-bit array .............. S ............. S
complex array ........ 0.1983 ........ 0.2339
fix map #1 ........... 0.0939 ........ 0.1083
fix map #2 ........... 0.0436 ........ 0.0419
fix map #3 ........... 0.0472 ........ 0.0608
fix map #4 ........... 0.0490 ........ 0.0457
16-bit map #1 ........ 0.2347 ........ 0.3041
16-bit map #2 ............. S ............. S
32-bit map ................ S ............. S
complex map .......... 0.2671 ........ 0.2711
fixext 1 ............. 0.0150 ........ 0.0340
fixext 2 ............. 0.0156 ........ 0.0391
fixext 4 ............. 0.0176 ........ 0.0362
fixext 8 ............. 0.0153 ........ 0.0362
fixext 16 ............ 0.0163 ........ 0.0356
8-bit ext ............ 0.0187 ........ 0.0428
16-bit ext ........... 0.0237 ........ 0.0478
32-bit ext ........... 0.1630 ........ 0.1924
=============================================
Total                  6.1434          3.6490
Skipped                     4               4
Failed                      0               0
Ignored                     0               0
```
</details>

*With JIT:*

```sh
php -n -dzend_extension=opcache.so \
-dpcre.jit=1 -dopcache.jit_buffer_size=64M -dopcache.jit=tracing -dopcache.enable=1 -dopcache.enable_cli=1 \
tests/bench.php
```

<details>
<summary><strong>Example output</strong></summary>

```
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

=============================================
Test/Target            Packer  BufferUnpacker
---------------------------------------------
nil .................. 0.0005 ........ 0.0060
false ................ 0.0013 ........ 0.0072
true ................. 0.0031 ........ 0.0107
7-bit uint #1 ........ 0.0029 ........ 0.0065
7-bit uint #2 ........ 0.0028 ........ 0.0065
7-bit uint #3 ........ 0.0030 ........ 0.0090
5-bit sint #1 ........ 0.0038 ........ 0.0100
5-bit sint #2 ........ 0.0038 ........ 0.0068
5-bit sint #3 ........ 0.0037 ........ 0.0067
8-bit uint #1 ........ 0.0069 ........ 0.0101
8-bit uint #2 ........ 0.0069 ........ 0.0100
8-bit uint #3 ........ 0.0069 ........ 0.0100
16-bit uint #1 ....... 0.0135 ........ 0.0118
16-bit uint #2 ....... 0.0097 ........ 0.0115
16-bit uint #3 ....... 0.0098 ........ 0.0115
32-bit uint #1 ....... 0.0109 ........ 0.0149
32-bit uint #2 ....... 0.0109 ........ 0.0148
32-bit uint #3 ....... 0.0107 ........ 0.0149
64-bit uint #1 ....... 0.0151 ........ 0.0233
64-bit uint #2 ....... 0.0111 ........ 0.0231
64-bit uint #3 ....... 0.0152 ........ 0.0232
8-bit int #1 ......... 0.0071 ........ 0.0106
8-bit int #2 ......... 0.0070 ........ 0.0099
8-bit int #3 ......... 0.0071 ........ 0.0105
16-bit int #1 ........ 0.0100 ........ 0.0160
16-bit int #2 ........ 0.0100 ........ 0.0115
16-bit int #3 ........ 0.0097 ........ 0.0114
32-bit int #1 ........ 0.0105 ........ 0.0153
32-bit int #2 ........ 0.0105 ........ 0.0248
32-bit int #3 ........ 0.0106 ........ 0.0151
64-bit int #1 ........ 0.0111 ........ 0.0293
64-bit int #2 ........ 0.0152 ........ 0.0236
64-bit int #3 ........ 0.0110 ........ 0.0236
64-bit int #4 ........ 0.0110 ........ 0.0236
64-bit float #1 ...... 0.0105 ........ 0.0285
64-bit float #2 ...... 0.0107 ........ 0.0227
64-bit float #3 ...... 0.0106 ........ 0.0228
fix string #1 ........ 0.0016 ........ 0.0067
fix string #2 ........ 0.0149 ........ 0.0110
fix string #3 ........ 0.0150 ........ 0.0124
fix string #4 ........ 0.0184 ........ 0.0135
8-bit string #1 ...... 0.0216 ........ 0.0160
8-bit string #2 ...... 0.0245 ........ 0.0163
8-bit string #3 ...... 0.0290 ........ 0.0164
16-bit string #1 ..... 0.0315 ........ 0.0182
16-bit string #2 ..... 1.9043 ........ 0.1571
32-bit string ........ 1.9010 ........ 0.1588
wide char string #1 .. 0.0155 ........ 0.0124
wide char string #2 .. 0.0228 ........ 0.0159
8-bit binary #1 ...... 0.0167 ........ 0.0144
8-bit binary #2 ...... 0.0168 ........ 0.0162
8-bit binary #3 ...... 0.0172 ........ 0.0214
16-bit binary ........ 0.0266 ........ 0.0180
32-bit binary ........ 0.1612 ........ 0.1586
fix array #1 ......... 0.0021 ........ 0.0077
fix array #2 ......... 0.0174 ........ 0.0200
fix array #3 ......... 0.0386 ........ 0.0288
16-bit array #1 ...... 0.0656 ........ 0.0546
16-bit array #2 ........... S ............. S
32-bit array .............. S ............. S
complex array ........ 0.1048 ........ 0.0874
fix map #1 ........... 0.0519 ........ 0.0478
fix map #2 ........... 0.0340 ........ 0.0260
fix map #3 ........... 0.0333 ........ 0.0374
fix map #4 ........... 0.0297 ........ 0.0292
16-bit map #1 ........ 0.0978 ........ 0.0992
16-bit map #2 ............. S ............. S
32-bit map ................ S ............. S
complex map .......... 0.1355 ........ 0.1166
fixext 1 ............. 0.0112 ........ 0.0214
fixext 2 ............. 0.0113 ........ 0.0233
fixext 4 ............. 0.0117 ........ 0.0230
fixext 8 ............. 0.0113 ........ 0.0231
fixext 16 ............ 0.0114 ........ 0.0236
8-bit ext ............ 0.0136 ........ 0.0320
16-bit ext ........... 0.0176 ........ 0.0335
32-bit ext ........... 0.1565 ........ 0.1666
=============================================
Total                  5.4089          2.1324
Skipped                     4               4
Failed                      0               0
Ignored                     0               0
```
</details>

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

Another example, benchmarking both the library and the [PECL extension](https://pecl.php.net/package/msgpack):

```sh
MP_BENCH_TARGETS=pure_ps,pure_bu,pecl_p,pecl_u \
php -n -dextension=msgpack.so -dzend_extension=opcache.so \
-dpcre.jit=1 -dopcache.enable=1 -dopcache.enable_cli=1 \
tests/bench.php
```

<details>
<summary><strong>Example output</strong></summary>

```
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

======================================================================================
Test/Target           Packer (force_str)  BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------------
nil ............................. 0.0025 ........ 0.0129 ...... 0.0037 ........ 0.0015
false ........................... 0.0028 ........ 0.0132 ...... 0.0037 ........ 0.0024
true ............................ 0.0030 ........ 0.0133 ...... 0.0044 ........ 0.0042
7-bit uint #1 ................... 0.0066 ........ 0.0127 ...... 0.0042 ........ 0.0024
7-bit uint #2 ................... 0.0062 ........ 0.0120 ...... 0.0045 ........ 0.0020
7-bit uint #3 ................... 0.0066 ........ 0.0125 ...... 0.0040 ........ 0.0022
5-bit sint #1 ................... 0.0067 ........ 0.0135 ...... 0.0050 ........ 0.0025
5-bit sint #2 ................... 0.0062 ........ 0.0133 ...... 0.0040 ........ 0.0016
5-bit sint #3 ................... 0.0067 ........ 0.0128 ...... 0.0044 ........ 0.0027
8-bit uint #1 ................... 0.0088 ........ 0.0229 ...... 0.0068 ........ 0.0027
8-bit uint #2 ................... 0.0093 ........ 0.0207 ...... 0.0062 ........ 0.0033
8-bit uint #3 ................... 0.0093 ........ 0.0204 ...... 0.0043 ........ 0.0028
16-bit uint #1 .................. 0.0120 ........ 0.0253 ...... 0.0050 ........ 0.0030
16-bit uint #2 .................. 0.0131 ........ 0.0242 ...... 0.0053 ........ 0.0034
16-bit uint #3 .................. 0.0131 ........ 0.0243 ...... 0.0053 ........ 0.0033
32-bit uint #1 .................. 0.0126 ........ 0.0332 ...... 0.0043 ........ 0.0024
32-bit uint #2 .................. 0.0121 ........ 0.0333 ...... 0.0041 ........ 0.0030
32-bit uint #3 .................. 0.0130 ........ 0.0327 ...... 0.0040 ........ 0.0029
64-bit uint #1 .................. 0.0138 ........ 0.0314 ...... 0.0040 ........ 0.0033
64-bit uint #2 .................. 0.0153 ........ 0.0303 ...... 0.0042 ........ 0.0047
64-bit uint #3 .................. 0.0132 ........ 0.0326 ...... 0.0054 ........ 0.0039
8-bit int #1 .................... 0.0118 ........ 0.0216 ...... 0.0036 ........ 0.0024
8-bit int #2 .................... 0.0093 ........ 0.0214 ...... 0.0037 ........ 0.0036
8-bit int #3 .................... 0.0084 ........ 0.0201 ...... 0.0062 ........ 0.0025
16-bit int #1 ................... 0.0137 ........ 0.0262 ...... 0.0036 ........ 0.0040
16-bit int #2 ................... 0.0151 ........ 0.0246 ...... 0.0059 ........ 0.0031
16-bit int #3 ................... 0.0132 ........ 0.0281 ...... 0.0045 ........ 0.0028
32-bit int #1 ................... 0.0151 ........ 0.0378 ...... 0.0050 ........ 0.0039
32-bit int #2 ................... 0.0137 ........ 0.0356 ...... 0.0040 ........ 0.0031
32-bit int #3 ................... 0.0134 ........ 0.0335 ...... 0.0048 ........ 0.0020
64-bit int #1 ................... 0.0135 ........ 0.0313 ...... 0.0043 ........ 0.0026
64-bit int #2 ................... 0.0135 ........ 0.0301 ...... 0.0043 ........ 0.0029
64-bit int #3 ................... 0.0131 ........ 0.0312 ...... 0.0046 ........ 0.0030
64-bit int #4 ................... 0.0160 ........ 0.0330 ...... 0.0044 ........ 0.0028
64-bit float #1 ................. 0.0147 ........ 0.0295 ...... 0.0051 ........ 0.0047
64-bit float #2 ................. 0.0145 ........ 0.0313 ...... 0.0040 ........ 0.0025
64-bit float #3 ................. 0.0142 ........ 0.0301 ...... 0.0042 ........ 0.0026
fix string #1 ................... 0.0027 ........ 0.0134 ...... 0.0052 ........ 0.0025
fix string #2 ................... 0.0108 ........ 0.0251 ...... 0.0054 ........ 0.0049
fix string #3 ................... 0.0106 ........ 0.0232 ...... 0.0053 ........ 0.0044
fix string #4 ................... 0.0108 ........ 0.0232 ...... 0.0066 ........ 0.0051
8-bit string #1 ................. 0.0125 ........ 0.0330 ...... 0.0055 ........ 0.0046
8-bit string #2 ................. 0.0124 ........ 0.0304 ...... 0.0047 ........ 0.0051
8-bit string #3 ................. 0.0106 ........ 0.0314 ...... 0.0097 ........ 0.0045
16-bit string #1 ................ 0.0162 ........ 0.0356 ...... 0.0103 ........ 0.0041
16-bit string #2 ................ 0.1552 ........ 0.1797 ...... 0.1457 ........ 0.1418
32-bit string ................... 0.1559 ........ 0.1813 ...... 0.1467 ........ 0.1425
wide char string #1 ............. 0.0100 ........ 0.0236 ...... 0.0053 ........ 0.0041
wide char string #2 ............. 0.0124 ........ 0.0303 ...... 0.0053 ........ 0.0061
8-bit binary #1 ...................... I ............. I ........... F ............. I
8-bit binary #2 ...................... I ............. I ........... F ............. I
8-bit binary #3 ...................... I ............. I ........... F ............. I
16-bit binary ........................ I ............. I ........... F ............. I
32-bit binary ........................ I ............. I ........... F ............. I
fix array #1 .................... 0.0040 ........ 0.0134 ...... 0.0141 ........ 0.0034
fix array #2 .................... 0.0292 ........ 0.0347 ...... 0.0149 ........ 0.0133
fix array #3 .................... 0.0443 ........ 0.0494 ...... 0.0169 ........ 0.0164
16-bit array #1 ................. 0.1390 ........ 0.1634 ...... 0.0290 ........ 0.0325
16-bit array #2 ...................... S ............. S ........... S ............. S
32-bit array ......................... S ............. S ........... S ............. S
complex array ........................ I ............. I ........... F ............. F
fix map #1 ........................... I ............. I ........... F ............. I
fix map #2 ...................... 0.0336 ........ 0.0407 ...... 0.0180 ........ 0.0159
fix map #3 ........................... I ............. I ........... F ............. I
fix map #4 ...................... 0.0455 ........ 0.0489 ...... 0.0163 ........ 0.0165
16-bit map #1 ................... 0.2265 ........ 0.3023 ...... 0.0320 ........ 0.0439
16-bit map #2 ........................ S ............. S ........... S ............. S
32-bit map ........................... S ............. S ........... S ............. S
complex map ..................... 0.2354 ........ 0.2730 ...... 0.0532 ........ 0.0520
fixext 1 ............................. I ............. I ........... F ............. F
fixext 2 ............................. I ............. I ........... F ............. F
fixext 4 ............................. I ............. I ........... F ............. F
fixext 8 ............................. I ............. I ........... F ............. F
fixext 16 ............................ I ............. I ........... F ............. F
8-bit ext ............................ I ............. I ........... F ............. F
16-bit ext ........................... I ............. I ........... F ............. F
32-bit ext ........................... I ............. I ........... F ............. F
======================================================================================
Total                             1.5836          2.4687        0.7192          0.6321
Skipped                                4               4             4               4
Failed                                 0               0            16               9
Ignored                               16              16             0               7
```
</details>


*With JIT:*

```sh
MP_BENCH_TARGETS=pure_ps,pure_bu,pecl_p,pecl_u \
php -n -dextension=msgpack.so -dzend_extension=opcache.so \
-dpcre.jit=1 -dopcache.jit_buffer_size=64M -dopcache.jit=tracing -dopcache.enable=1 -dopcache.enable_cli=1 \
tests/bench.php
```

<details>
<summary><strong>Example output</strong></summary>

```
Filter: MessagePack\Tests\Perf\Filter\ListFilter
Rounds: 3
Iterations: 100000

======================================================================================
Test/Target           Packer (force_str)  BufferUnpacker  msgpack_pack  msgpack_unpack
--------------------------------------------------------------------------------------
nil ............................. 0.0003 ........ 0.0061 ...... 0.0064 ........ 0.0047
false ........................... 0.0015 ........ 0.0070 ...... 0.0065 ........ 0.0047
true ............................ 0.0017 ........ 0.0072 ...... 0.0107 ........ 0.0050
7-bit uint #1 ................... 0.0032 ........ 0.0065 ...... 0.0080 ........ 0.0044
7-bit uint #2 ................... 0.0033 ........ 0.0064 ...... 0.0082 ........ 0.0044
7-bit uint #3 ................... 0.0034 ........ 0.0063 ...... 0.0079 ........ 0.0045
5-bit sint #1 ................... 0.0047 ........ 0.0067 ...... 0.0081 ........ 0.0076
5-bit sint #2 ................... 0.0045 ........ 0.0068 ...... 0.0152 ........ 0.0047
5-bit sint #3 ................... 0.0044 ........ 0.0068 ...... 0.0080 ........ 0.0046
8-bit uint #1 ................... 0.0075 ........ 0.0096 ...... 0.0082 ........ 0.0051
8-bit uint #2 ................... 0.0078 ........ 0.0098 ...... 0.0080 ........ 0.0082
8-bit uint #3 ................... 0.0076 ........ 0.0096 ...... 0.0108 ........ 0.0051
16-bit uint #1 .................. 0.0109 ........ 0.0121 ...... 0.0081 ........ 0.0053
16-bit uint #2 .................. 0.0106 ........ 0.0160 ...... 0.0101 ........ 0.0050
16-bit uint #3 .................. 0.0109 ........ 0.0118 ...... 0.0081 ........ 0.0050
32-bit uint #1 .................. 0.0112 ........ 0.0153 ...... 0.0081 ........ 0.0050
32-bit uint #2 .................. 0.0111 ........ 0.0150 ...... 0.0081 ........ 0.0049
32-bit uint #3 .................. 0.0113 ........ 0.0151 ...... 0.0080 ........ 0.0049
64-bit uint #1 .................. 0.0116 ........ 0.0233 ...... 0.0082 ........ 0.0051
64-bit uint #2 .................. 0.0160 ........ 0.0234 ...... 0.0079 ........ 0.0054
64-bit uint #3 .................. 0.0116 ........ 0.0234 ...... 0.0080 ........ 0.0052
8-bit int #1 .................... 0.0076 ........ 0.0107 ...... 0.0081 ........ 0.0085
8-bit int #2 .................... 0.0077 ........ 0.0100 ...... 0.0155 ........ 0.0054
8-bit int #3 .................... 0.0077 ........ 0.0107 ...... 0.0079 ........ 0.0081
16-bit int #1 ................... 0.0105 ........ 0.0119 ...... 0.0082 ........ 0.0050
16-bit int #2 ................... 0.0103 ........ 0.0118 ...... 0.0081 ........ 0.0080
16-bit int #3 ................... 0.0108 ........ 0.0118 ...... 0.0108 ........ 0.0050
32-bit int #1 ................... 0.0112 ........ 0.0205 ...... 0.0116 ........ 0.0052
32-bit int #2 ................... 0.0109 ........ 0.0153 ...... 0.0078 ........ 0.0050
32-bit int #3 ................... 0.0112 ........ 0.0154 ...... 0.0082 ........ 0.0078
64-bit int #1 ................... 0.0118 ........ 0.0235 ...... 0.0153 ........ 0.0052
64-bit int #2 ................... 0.0117 ........ 0.0237 ...... 0.0080 ........ 0.0048
64-bit int #3 ................... 0.0117 ........ 0.0238 ...... 0.0080 ........ 0.0050
64-bit int #4 ................... 0.0119 ........ 0.0235 ...... 0.0082 ........ 0.0046
64-bit float #1 ................. 0.0108 ........ 0.0286 ...... 0.0145 ........ 0.0052
64-bit float #2 ................. 0.0107 ........ 0.0230 ...... 0.0076 ........ 0.0051
64-bit float #3 ................. 0.0108 ........ 0.0218 ...... 0.0076 ........ 0.0051
fix string #1 ................... 0.0019 ........ 0.0068 ...... 0.0084 ........ 0.0051
fix string #2 ................... 0.0070 ........ 0.0108 ...... 0.0085 ........ 0.0069
fix string #3 ................... 0.0071 ........ 0.0122 ...... 0.0088 ........ 0.0069
fix string #4 ................... 0.0106 ........ 0.0120 ...... 0.0084 ........ 0.0066
8-bit string #1 ................. 0.0104 ........ 0.0208 ...... 0.0122 ........ 0.0074
8-bit string #2 ................. 0.0108 ........ 0.0159 ...... 0.0086 ........ 0.0070
8-bit string #3 ................. 0.0111 ........ 0.0162 ...... 0.0165 ........ 0.0073
16-bit string #1 ................ 0.0141 ........ 0.0181 ...... 0.0144 ........ 0.0090
16-bit string #2 ................ 0.1550 ........ 0.1644 ...... 0.1534 ........ 0.1488
32-bit string ................... 0.1547 ........ 0.1591 ...... 0.1572 ........ 0.1561
wide char string #1 ............. 0.0070 ........ 0.0118 ...... 0.0084 ........ 0.0070
wide char string #2 ............. 0.0106 ........ 0.0161 ...... 0.0089 ........ 0.0112
8-bit binary #1 ...................... I ............. I ........... F ............. I
8-bit binary #2 ...................... I ............. I ........... F ............. I
8-bit binary #3 ...................... I ............. I ........... F ............. I
16-bit binary ........................ I ............. I ........... F ............. I
32-bit binary ........................ I ............. I ........... F ............. I
fix array #1 .................... 0.0024 ........ 0.0075 ...... 0.0163 ........ 0.0063
fix array #2 .................... 0.0179 ........ 0.0198 ...... 0.0192 ........ 0.0176
fix array #3 .................... 0.0261 ........ 0.0340 ...... 0.0231 ........ 0.0269
16-bit array #1 ................. 0.0662 ........ 0.0546 ...... 0.0345 ........ 0.0391
16-bit array #2 ...................... S ............. S ........... S ............. S
32-bit array ......................... S ............. S ........... S ............. S
complex array ........................ I ............. I ........... F ............. F
fix map #1 ........................... I ............. I ........... F ............. I
fix map #2 ...................... 0.0214 ........ 0.0306 ...... 0.0197 ........ 0.0207
fix map #3 ........................... I ............. I ........... F ............. I
fix map #4 ...................... 0.0292 ........ 0.0270 ...... 0.0306 ........ 0.0209
16-bit map #1 ................... 0.1031 ........ 0.0896 ...... 0.0378 ........ 0.0506
16-bit map #2 ........................ S ............. S ........... S ............. S
32-bit map ........................... S ............. S ........... S ............. S
complex map ..................... 0.1164 ........ 0.1131 ...... 0.0591 ........ 0.0583
fixext 1 ............................. I ............. I ........... F ............. F
fixext 2 ............................. I ............. I ........... F ............. F
fixext 4 ............................. I ............. I ........... F ............. F
fixext 8 ............................. I ............. I ........... F ............. F
fixext 16 ............................ I ............. I ........... F ............. F
8-bit ext ............................ I ............. I ........... F ............. F
16-bit ext ........................... I ............. I ........... F ............. F
32-bit ext ........................... I ............. I ........... F ............. F
======================================================================================
Total                             1.1056          1.3705        0.9900          0.8214
Skipped                                4               4             4               4
Failed                                 0               0            16               9
Ignored                               16              16             0               7
```
</details>

> *Note that the msgpack extension (v2.1.2) doesn't support **ext**, **bin** and UTF-8 **str** types.*


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
