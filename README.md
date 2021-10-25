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
 * Supports [object serialization](#custom-types)
 * [Fully tested](https://github.com/rybakit/msgpack.php/actions?query=workflow%3AQA)
 * [Relatively fast](#performance)


## Table of contents

 * [Installation](#installation)
 * [Usage](#usage)
   * [Packing](#packing)
     * [Packing options](#packing-options)
   * [Unpacking](#unpacking)
     * [Unpacking options](#unpacking-options)
 * [Custom types](#custom-types)
   * [Type objects](#type-objects)
   * [Type transformers](#type-transformers)
   * [Extensions](#extensions)
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

In the examples above, the method `pack` automatically packs a value depending 
on its type. However, not all PHP types can be uniquely translated to MessagePack 
types. For example, the MessagePack format defines `map` and `array` types, 
which are represented by a single `array` type in PHP. By default, the packer 
will pack a PHP array as a MessagePack array if it has sequential numeric keys, 
starting from `0` and as a MessagePack map otherwise:

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

> *Check the ["Custom types"](#custom-types) section below on how to pack custom types.*


#### Packing options

The `Packer` object supports a number of bitmask-based options for fine-tuning 
the packing process (defaults are in bold):

| Name                 | Description                                                   |
| -------------------- | ------------------------------------------------------------- |
| **`FORCE_STR`**      | Forces PHP strings to be packed as MessagePack UTF-8 strings  |
| `FORCE_BIN`          | Forces PHP strings to be packed as MessagePack binary data    |
| `DETECT_STR_BIN`     | Detects MessagePack str/bin type automatically                |
|                      |                                                               |
| `FORCE_ARR`          | Forces PHP arrays to be packed as MessagePack arrays          |
| `FORCE_MAP`          | Forces PHP arrays to be packed as MessagePack maps            |
| **`DETECT_ARR_MAP`** | Detects MessagePack array/map type automatically              |
|                      |                                                               |
| `FORCE_FLOAT32`      | Forces PHP floats to be packed as 32-bits MessagePack floats  |
| **`FORCE_FLOAT64`**  | Forces PHP floats to be packed as 64-bits MessagePack floats  |

> *The type detection mode (`DETECT_STR_BIN`/`DETECT_ARR_MAP`) adds some overhead 
> which can be noticed when you pack large (16- and 32-bit) arrays or strings. 
> However, if you know the value type in advance (for example, you only work with 
> UTF-8 strings or/and associative arrays), you can eliminate this overhead by 
> forcing the packer to use the appropriate type, which will save it from running 
> the auto-detection routine. Another option is to explicitly specify the value 
> type. The library provides 2 auxiliary classes for this, [Map](src/Type/Map.php) 
> and [Bin](src/Type/Bin.php). Check the ["Custom types"](#custom-types) section 
> below for details.*

Examples:

```php
use MessagePack\Packer;
use MessagePack\PackOptions;

// detect str/bin type and pack PHP 64-bit floats (doubles) to MP 32-bit floats
$packer = new Packer(PackOptions::DETECT_STR_BIN | PackOptions::FORCE_FLOAT32);

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

If the packed data is received in chunks (e.g. when reading from a stream), 
use the `tryUnpack` method, which attempts to unpack data and returns an array 
of unpacked messages (if any) instead of throwing 
an [InsufficientDataException](src/Exception/InsufficientDataException.php):

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

Besides the above methods `BufferUnpacker` provides type-specific unpacking 
methods, namely:

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

The `BufferUnpacker` object supports a number of bitmask-based options for 
fine-tuning the unpacking process (defaults are in bold):

| Name                | Description                                                              |
| ------------------- | ------------------------------------------------------------------------ |
| **`BIGINT_AS_STR`** | Converts overflowed integers to strings <sup>[1]</sup>                   |
| `BIGINT_AS_GMP`     | Converts overflowed integers to `GMP` objects <sup>[2]</sup>             |
| `BIGINT_AS_DEC`     | Converts overflowed integers to `Decimal\Decimal` objects <sup>[3]</sup> |

> *1. The binary MessagePack format has unsigned 64-bit as its largest integer 
>     data type, but PHP does not support such integers, which means that 
>     an overflow can occur during unpacking.*
>
> *2. Make sure the [GMP](http://php.net/manual/en/book.gmp.php) extension 
>     is enabled.*
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


### Custom types

In addition to the [basic types](https://github.com/msgpack/msgpack/blob/master/spec.md#type-system),
the library provides functionality to serialize and deserialize arbitrary types.
This can be done in several ways, depending on your use case. Let's take a look at them.

#### Type objects

If you need to *serialize* an instance of one of your classes, the best way to do it is to implement 
the [CanBePacked](src/CanBePacked.php) interface in the class. A good example of such a class is 
the [Map](src/Type/Map.php) type class that comes with the library. This type is useful when you want 
to explicitly specify that a given PHP array should be packed as a MessagePack map without triggering 
an automatic type detection routine:

```php
use MessagePack\Packer;
use MessagePack\Type\Map;

$packer = new Packer();

$packedMap = $packer->pack(new Map([1, 2, 3]));
$packedArray = $packer->pack([1, 2, 3]);
```

> *More type examples can be found in the [src/Type](src/Type) directory.*

#### Type transformers

As with type objects, type transformers are only responsible for *serializing* values. They should be 
used when you need to serialize a value that does not implement the [CanBePacked](src/CanBePacked.php) 
interface. Examples of such values could be instances of built-in or third-party classes that you don't 
own, or non-objects such as resources. 

A transformer class must implement the [CanPack](src/CanPack.php) interface. To use a transformer, 
it must first be registered in the packer. Here is an example of how to serialize PHP streams into 
the MessagePack `bin` format type using one of the supplied transformers, 
[StreamTransformer](src/TypeTransformer/StreamTransformer.php):

```php
use MessagePack\Packer;
use MessagePack\TypeTransformer\StreamTransformer;

$packer = new Packer(null, [new StreamTransformer()]);

$packedBin = $packer->pack(fopen('/path/to/file', 'r+'));
```

> *More type transformer examples can be found in the [src/TypeTransformer](src/TypeTransformer) directory.*

#### Extensions

In contrast to the cases described above, extensions are intended to handle
[extension types](https://github.com/msgpack/msgpack/blob/master/spec.md#extension-types)
and are responsible for *serializing* and *deserializing* values. An extension class must implement 
the [Extension](src/Extension.php) interface.

For example, to make the built-in PHP `DateTime` objects first-class citizens in your code, you can 
create a corresponding extension, as shown in the [example](examples/MessagePack/DateTimeExtension.php). 
Register the extension for both the packer and the unpacker with a unique extension type (an integer 
from 0 to 127) and you're ready to go:

```php
use App\MessagePack\DateTimeExtension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

$dateTimeExtension = new DateTimeExtension(42);

$packer = new Packer();
$packer = $packer->extendWith($dateTimeExtension);

$unpacker = new BufferUnpacker();
$unpacker = $unpacker->extendWith($dateTimeExtension);

$packedDate = $packer->pack(new DateTimeImmutable());
$originalDate = $unpacker->reset($packedDate)->unpack();
```

If you unpack a value from an extension that is not known to the unpacker, an [Ext](src/Type/Ext.php) 
object will be returned. It can also be used to pack an extension:

```php
use MessagePack\Ext;
use MessagePack\MessagePack;

$packed = MessagePack::pack(new Ext(42, "\xaa"));
$ext = MessagePack::unpack($packed);

assert($ext->type === 42);
assert($ext->data === "\xaa");
```

> *More extension examples can be found in the [examples/MessagePack](examples/MessagePack) directory.*

> *To learn more about how extension types can be useful, check out this 
> [article](https://dev.to/tarantool/advanced-messagepack-capabilities-4735).*


## Exceptions

If an error occurs during packing/unpacking, 
a [PackingFailedException](src/Exception/PackingFailedException.php) or 
an [UnpackingFailedException](src/Exception/UnpackingFailedException.php) will be thrown, respectively. 
In addition, an [InsufficientDataException](src/Exception/InsufficientDataException.php) can be thrown 
during unpacking.

An [InvalidOptionException](src/Exception/InvalidOptionException.php) will be thrown in case an invalid 
option (or a combination of mutually exclusive options) is used.


## Tests

Run tests as follows:

```sh
vendor/bin/phpunit
```

Also, if you already have Docker installed, you can run the tests in a docker 
container. First, create a container:

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
nil .................. 0.0017 ........ 0.0138
false ................ 0.0033 ........ 0.0131
true ................. 0.0026 ........ 0.0136
7-bit uint #1 ........ 0.0079 ........ 0.0125
7-bit uint #2 ........ 0.0077 ........ 0.0126
7-bit uint #3 ........ 0.0066 ........ 0.0128
5-bit sint #1 ........ 0.0080 ........ 0.0140
5-bit sint #2 ........ 0.0074 ........ 0.0134
5-bit sint #3 ........ 0.0074 ........ 0.0183
8-bit uint #1 ........ 0.0085 ........ 0.0211
8-bit uint #2 ........ 0.0092 ........ 0.0226
8-bit uint #3 ........ 0.0085 ........ 0.0208
16-bit uint #1 ....... 0.0170 ........ 0.0245
16-bit uint #2 ....... 0.0130 ........ 0.0250
16-bit uint #3 ....... 0.0130 ........ 0.0249
32-bit uint #1 ....... 0.0132 ........ 0.0341
32-bit uint #2 ....... 0.0124 ........ 0.0343
32-bit uint #3 ....... 0.0142 ........ 0.0323
64-bit uint #1 ....... 0.0137 ........ 0.0314
64-bit uint #2 ....... 0.0129 ........ 0.0309
64-bit uint #3 ....... 0.0153 ........ 0.0320
8-bit int #1 ......... 0.0104 ........ 0.0217
8-bit int #2 ......... 0.0108 ........ 0.0236
8-bit int #3 ......... 0.0088 ........ 0.0244
16-bit int #1 ........ 0.0135 ........ 0.0245
16-bit int #2 ........ 0.0134 ........ 0.0254
16-bit int #3 ........ 0.0139 ........ 0.0252
32-bit int #1 ........ 0.0133 ........ 0.0329
32-bit int #2 ........ 0.0154 ........ 0.0364
32-bit int #3 ........ 0.0131 ........ 0.0330
64-bit int #1 ........ 0.0141 ........ 0.0312
64-bit int #2 ........ 0.0137 ........ 0.0345
64-bit int #3 ........ 0.0128 ........ 0.0335
64-bit int #4 ........ 0.0141 ........ 0.0313
64-bit float #1 ...... 0.0148 ........ 0.0300
64-bit float #2 ...... 0.0147 ........ 0.0308
64-bit float #3 ...... 0.0145 ........ 0.0302
fix string #1 ....... -0.0032 ........ 0.0127
fix string #2 ........ 0.0102 ........ 0.0250
fix string #3 ........ 0.0132 ........ 0.0240
fix string #4 ........ 0.0122 ........ 0.0243
8-bit string #1 ...... 0.0121 ........ 0.0316
8-bit string #2 ...... 0.0128 ........ 0.0325
8-bit string #3 ...... 0.0146 ........ 0.0312
16-bit string #1 ..... 0.0185 ........ 0.0353
16-bit string #2 ..... 0.1541 ........ 0.1720
32-bit string ........ 0.1541 ........ 0.1801
wide char string #1 .. 0.0110 ........ 0.0260
wide char string #2 .. 0.0127 ........ 0.0334
8-bit binary #1 ...... 0.0107 ........ 0.0293
8-bit binary #2 ...... 0.0121 ........ 0.0304
8-bit binary #3 ...... 0.0131 ........ 0.0305
16-bit binary ........ 0.0159 ........ 0.0355
32-bit binary ........ 0.1564 ........ 0.1825
fix array #1 ......... 0.0025 ........ 0.0128
fix array #2 ......... 0.0296 ........ 0.0354
fix array #3 ......... 0.0436 ........ 0.0505
16-bit array #1 ...... 0.1416 ........ 0.1621
16-bit array #2 ........... S ............. S
32-bit array .............. S ............. S
complex array ........ 0.1695 ........ 0.2323
fix map #1 ........... 0.0776 ........ 0.1083
fix map #2 ........... 0.0368 ........ 0.0419
fix map #3 ........... 0.0407 ........ 0.0603
fix map #4 ........... 0.0454 ........ 0.0527
16-bit map #1 ........ 0.2320 ........ 0.3022
16-bit map #2 ............. S ............. S
32-bit map ................ S ............. S
complex map .......... 0.2327 ........ 0.2729
fixext 1 ............. 0.0156 ........ 0.0371
fixext 2 ............. 0.0154 ........ 0.0360
fixext 4 ............. 0.0185 ........ 0.0358
fixext 8 ............. 0.0150 ........ 0.0361
fixext 16 ............ 0.0183 ........ 0.0361
8-bit ext ............ 0.0189 ........ 0.0433
16-bit ext ........... 0.0208 ........ 0.0467
32-bit ext ........... 0.1595 ........ 0.1927
=============================================
Total                  2.3793          3.6580
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
nil .................. 0.0002 ........ 0.0058
false ................ 0.0012 ........ 0.0072
true ................. 0.0013 ........ 0.0075
7-bit uint #1 ........ 0.0027 ........ 0.0067
7-bit uint #2 ........ 0.0028 ........ 0.0066
7-bit uint #3 ........ 0.0029 ........ 0.0068
5-bit sint #1 ........ 0.0039 ........ 0.0098
5-bit sint #2 ........ 0.0036 ........ 0.0067
5-bit sint #3 ........ 0.0065 ........ 0.0071
8-bit uint #1 ........ 0.0068 ........ 0.0100
8-bit uint #2 ........ 0.0066 ........ 0.0100
8-bit uint #3 ........ 0.0062 ........ 0.0099
16-bit uint #1 ....... 0.0096 ........ 0.0119
16-bit uint #2 ....... 0.0097 ........ 0.0117
16-bit uint #3 ....... 0.0096 ........ 0.0116
32-bit uint #1 ....... 0.0105 ........ 0.0155
32-bit uint #2 ....... 0.0136 ........ 0.0148
32-bit uint #3 ....... 0.0106 ........ 0.0148
64-bit uint #1 ....... 0.0111 ........ 0.0232
64-bit uint #2 ....... 0.0111 ........ 0.0231
64-bit uint #3 ....... 0.0109 ........ 0.0231
8-bit int #1 ......... 0.0103 ........ 0.0108
8-bit int #2 ......... 0.0067 ........ 0.0106
8-bit int #3 ......... 0.0067 ........ 0.0106
16-bit int #1 ........ 0.0095 ........ 0.0118
16-bit int #2 ........ 0.0136 ........ 0.0116
16-bit int #3 ........ 0.0097 ........ 0.0159
32-bit int #1 ........ 0.0107 ........ 0.0153
32-bit int #2 ........ 0.0105 ........ 0.0152
32-bit int #3 ........ 0.0106 ........ 0.0151
64-bit int #1 ........ 0.0111 ........ 0.0236
64-bit int #2 ........ 0.0154 ........ 0.0235
64-bit int #3 ........ 0.0153 ........ 0.0236
64-bit int #4 ........ 0.0111 ........ 0.0292
64-bit float #1 ...... 0.0146 ........ 0.0230
64-bit float #2 ...... 0.0104 ........ 0.0228
64-bit float #3 ...... 0.0104 ........ 0.0228
fix string #1 ........ 0.0016 ........ 0.0066
fix string #2 ........ 0.0066 ........ 0.0147
fix string #3 ........ 0.0066 ........ 0.0167
fix string #4 ........ 0.0063 ........ 0.0119
8-bit string #1 ...... 0.0143 ........ 0.0163
8-bit string #2 ...... 0.0103 ........ 0.0212
8-bit string #3 ...... 0.0106 ........ 0.0161
16-bit string #1 ..... 0.0138 ........ 0.0237
16-bit string #2 ..... 0.1612 ........ 0.1572
32-bit string ........ 0.1549 ........ 0.1693
wide char string #1 .. 0.0064 ........ 0.0164
wide char string #2 .. 0.0098 ........ 0.0162
8-bit binary #1 ...... 0.0097 ........ 0.0143
8-bit binary #2 ...... 0.0101 ........ 0.0161
8-bit binary #3 ...... 0.0104 ........ 0.0212
16-bit binary ........ 0.0137 ........ 0.0184
32-bit binary ........ 0.1549 ........ 0.1589
fix array #1 ......... 0.0014 ........ 0.0072
fix array #2 ......... 0.0173 ........ 0.0205
fix array #3 ......... 0.0257 ........ 0.0288
16-bit array #1 ...... 0.0713 ........ 0.0561
16-bit array #2 ........... S ............. S
32-bit array .............. S ............. S
complex array ........ 0.0831 ........ 0.0896
fix map #1 ........... 0.0385 ........ 0.0490
fix map #2 ........... 0.0204 ........ 0.0254
fix map #3 ........... 0.0255 ........ 0.0313
fix map #4 ........... 0.0294 ........ 0.0300
16-bit map #1 ........ 0.0956 ........ 0.1025
16-bit map #2 ............. S ............. S
32-bit map ................ S ............. S
complex map .......... 0.1161 ........ 0.1267
fixext 1 ............. 0.0157 ........ 0.0304
fixext 2 ............. 0.0121 ........ 0.0232
fixext 4 ............. 0.0117 ........ 0.0229
fixext 8 ............. 0.0118 ........ 0.0233
fixext 16 ............ 0.0114 ........ 0.0245
8-bit ext ............ 0.0130 ........ 0.0266
16-bit ext ........... 0.0162 ........ 0.0275
32-bit ext ........... 0.1561 ........ 0.1665
=============================================
Total                  1.6916          2.1562
Skipped                     4               4
Failed                      0               0
Ignored                     0               0
```
</details>

You may change default benchmark settings by defining the following environment 
variables:

Name | Default
---- | -------
MP_BENCH_TARGETS | `pure_p,pure_u`, *see a [list](tests/bench.php#L83) of available targets*
MP_BENCH_ITERATIONS | `100_000`
MP_BENCH_DURATION | *not set*
MP_BENCH_ROUNDS | `3`
MP_BENCH_TESTS | `-@slow`, *see a [list](tests/DataProvider.php) of available tests*


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
```

Another example, benchmarking both the library and the [PECL extension](https://pecl.php.net/package/msgpack):

```sh
MP_BENCH_TARGETS=pure_p,pure_u,pecl_p,pecl_u \
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

===========================================================================
Test/Target            Packer  BufferUnpacker  msgpack_pack  msgpack_unpack
---------------------------------------------------------------------------
nil .................. 0.0025 ........ 0.0129 ...... 0.0037 ........ 0.0015
false ................ 0.0028 ........ 0.0132 ...... 0.0037 ........ 0.0024
true ................. 0.0030 ........ 0.0133 ...... 0.0044 ........ 0.0042
7-bit uint #1 ........ 0.0066 ........ 0.0127 ...... 0.0042 ........ 0.0024
7-bit uint #2 ........ 0.0062 ........ 0.0120 ...... 0.0045 ........ 0.0020
7-bit uint #3 ........ 0.0066 ........ 0.0125 ...... 0.0040 ........ 0.0022
5-bit sint #1 ........ 0.0067 ........ 0.0135 ...... 0.0050 ........ 0.0025
5-bit sint #2 ........ 0.0062 ........ 0.0133 ...... 0.0040 ........ 0.0016
5-bit sint #3 ........ 0.0067 ........ 0.0128 ...... 0.0044 ........ 0.0027
8-bit uint #1 ........ 0.0088 ........ 0.0229 ...... 0.0068 ........ 0.0027
8-bit uint #2 ........ 0.0093 ........ 0.0207 ...... 0.0062 ........ 0.0033
8-bit uint #3 ........ 0.0093 ........ 0.0204 ...... 0.0043 ........ 0.0028
16-bit uint #1 ....... 0.0120 ........ 0.0253 ...... 0.0050 ........ 0.0030
16-bit uint #2 ....... 0.0131 ........ 0.0242 ...... 0.0053 ........ 0.0034
16-bit uint #3 ....... 0.0131 ........ 0.0243 ...... 0.0053 ........ 0.0033
32-bit uint #1 ....... 0.0126 ........ 0.0332 ...... 0.0043 ........ 0.0024
32-bit uint #2 ....... 0.0121 ........ 0.0333 ...... 0.0041 ........ 0.0030
32-bit uint #3 ....... 0.0130 ........ 0.0327 ...... 0.0040 ........ 0.0029
64-bit uint #1 ....... 0.0138 ........ 0.0314 ...... 0.0040 ........ 0.0033
64-bit uint #2 ....... 0.0153 ........ 0.0303 ...... 0.0042 ........ 0.0047
64-bit uint #3 ....... 0.0132 ........ 0.0326 ...... 0.0054 ........ 0.0039
8-bit int #1 ......... 0.0118 ........ 0.0216 ...... 0.0036 ........ 0.0024
8-bit int #2 ......... 0.0093 ........ 0.0214 ...... 0.0037 ........ 0.0036
8-bit int #3 ......... 0.0084 ........ 0.0201 ...... 0.0062 ........ 0.0025
16-bit int #1 ........ 0.0137 ........ 0.0262 ...... 0.0036 ........ 0.0040
16-bit int #2 ........ 0.0151 ........ 0.0246 ...... 0.0059 ........ 0.0031
16-bit int #3 ........ 0.0132 ........ 0.0281 ...... 0.0045 ........ 0.0028
32-bit int #1 ........ 0.0151 ........ 0.0378 ...... 0.0050 ........ 0.0039
32-bit int #2 ........ 0.0137 ........ 0.0356 ...... 0.0040 ........ 0.0031
32-bit int #3 ........ 0.0134 ........ 0.0335 ...... 0.0048 ........ 0.0020
64-bit int #1 ........ 0.0135 ........ 0.0313 ...... 0.0043 ........ 0.0026
64-bit int #2 ........ 0.0135 ........ 0.0301 ...... 0.0043 ........ 0.0029
64-bit int #3 ........ 0.0131 ........ 0.0312 ...... 0.0046 ........ 0.0030
64-bit int #4 ........ 0.0160 ........ 0.0330 ...... 0.0044 ........ 0.0028
64-bit float #1 ...... 0.0147 ........ 0.0295 ...... 0.0051 ........ 0.0047
64-bit float #2 ...... 0.0145 ........ 0.0313 ...... 0.0040 ........ 0.0025
64-bit float #3 ...... 0.0142 ........ 0.0301 ...... 0.0042 ........ 0.0026
fix string #1 ........ 0.0027 ........ 0.0134 ...... 0.0052 ........ 0.0025
fix string #2 ........ 0.0108 ........ 0.0251 ...... 0.0054 ........ 0.0049
fix string #3 ........ 0.0106 ........ 0.0232 ...... 0.0053 ........ 0.0044
fix string #4 ........ 0.0108 ........ 0.0232 ...... 0.0066 ........ 0.0051
8-bit string #1 ...... 0.0125 ........ 0.0330 ...... 0.0055 ........ 0.0046
8-bit string #2 ...... 0.0124 ........ 0.0304 ...... 0.0047 ........ 0.0051
8-bit string #3 ...... 0.0106 ........ 0.0314 ...... 0.0097 ........ 0.0045
16-bit string #1 ..... 0.0162 ........ 0.0356 ...... 0.0103 ........ 0.0041
16-bit string #2 ..... 0.1552 ........ 0.1797 ...... 0.1457 ........ 0.1418
32-bit string ........ 0.1559 ........ 0.1813 ...... 0.1467 ........ 0.1425
wide char string #1 .. 0.0100 ........ 0.0236 ...... 0.0053 ........ 0.0041
wide char string #2 .. 0.0124 ........ 0.0303 ...... 0.0053 ........ 0.0061
8-bit binary #1 ........... I ............. I ........... F ............. I
8-bit binary #2 ........... I ............. I ........... F ............. I
8-bit binary #3 ........... I ............. I ........... F ............. I
16-bit binary ............. I ............. I ........... F ............. I
32-bit binary ............. I ............. I ........... F ............. I
fix array #1 ......... 0.0040 ........ 0.0134 ...... 0.0141 ........ 0.0034
fix array #2 ......... 0.0292 ........ 0.0347 ...... 0.0149 ........ 0.0133
fix array #3 ......... 0.0443 ........ 0.0494 ...... 0.0169 ........ 0.0164
16-bit array #1 ...... 0.1390 ........ 0.1634 ...... 0.0290 ........ 0.0325
16-bit array #2 ........... S ............. S ........... S ............. S
32-bit array .............. S ............. S ........... S ............. S
complex array ............. I ............. I ........... F ............. F
fix map #1 ................ I ............. I ........... F ............. I
fix map #2 ........... 0.0336 ........ 0.0407 ...... 0.0180 ........ 0.0159
fix map #3 ................ I ............. I ........... F ............. I
fix map #4 ........... 0.0455 ........ 0.0489 ...... 0.0163 ........ 0.0165
16-bit map #1 ........ 0.2265 ........ 0.3023 ...... 0.0320 ........ 0.0439
16-bit map #2 ............. S ............. S ........... S ............. S
32-bit map ................ S ............. S ........... S ............. S
complex map .......... 0.2354 ........ 0.2730 ...... 0.0532 ........ 0.0520
fixext 1 .................. I ............. I ........... F ............. F
fixext 2 .................. I ............. I ........... F ............. F
fixext 4 .................. I ............. I ........... F ............. F
fixext 8 .................. I ............. I ........... F ............. F
fixext 16 ................. I ............. I ........... F ............. F
8-bit ext ................. I ............. I ........... F ............. F
16-bit ext ................ I ............. I ........... F ............. F
32-bit ext ................ I ............. I ........... F ............. F
===========================================================================
Total                  1.5836          2.4687        0.7192          0.6321
Skipped                     4               4             4               4
Failed                      0               0            16               9
Ignored                    16              16             0               7
```
</details>


*With JIT:*

```sh
MP_BENCH_TARGETS=pure_p,pure_u,pecl_p,pecl_u \
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

===========================================================================
Test/Target            Packer  BufferUnpacker  msgpack_pack  msgpack_unpack
---------------------------------------------------------------------------
nil .................. 0.0003 ........ 0.0061 ...... 0.0064 ........ 0.0047
false ................ 0.0015 ........ 0.0070 ...... 0.0065 ........ 0.0047
true ................. 0.0017 ........ 0.0072 ...... 0.0107 ........ 0.0050
7-bit uint #1 ........ 0.0032 ........ 0.0065 ...... 0.0080 ........ 0.0044
7-bit uint #2 ........ 0.0033 ........ 0.0064 ...... 0.0082 ........ 0.0044
7-bit uint #3 ........ 0.0034 ........ 0.0063 ...... 0.0079 ........ 0.0045
5-bit sint #1 ........ 0.0047 ........ 0.0067 ...... 0.0081 ........ 0.0076
5-bit sint #2 ........ 0.0045 ........ 0.0068 ...... 0.0152 ........ 0.0047
5-bit sint #3 ........ 0.0044 ........ 0.0068 ...... 0.0080 ........ 0.0046
8-bit uint #1 ........ 0.0075 ........ 0.0096 ...... 0.0082 ........ 0.0051
8-bit uint #2 ........ 0.0078 ........ 0.0098 ...... 0.0080 ........ 0.0082
8-bit uint #3 ........ 0.0076 ........ 0.0096 ...... 0.0108 ........ 0.0051
16-bit uint #1 ....... 0.0109 ........ 0.0121 ...... 0.0081 ........ 0.0053
16-bit uint #2 ....... 0.0106 ........ 0.0160 ...... 0.0101 ........ 0.0050
16-bit uint #3 ....... 0.0109 ........ 0.0118 ...... 0.0081 ........ 0.0050
32-bit uint #1 ....... 0.0112 ........ 0.0153 ...... 0.0081 ........ 0.0050
32-bit uint #2 ....... 0.0111 ........ 0.0150 ...... 0.0081 ........ 0.0049
32-bit uint #3 ....... 0.0113 ........ 0.0151 ...... 0.0080 ........ 0.0049
64-bit uint #1 ....... 0.0116 ........ 0.0233 ...... 0.0082 ........ 0.0051
64-bit uint #2 ....... 0.0160 ........ 0.0234 ...... 0.0079 ........ 0.0054
64-bit uint #3 ....... 0.0116 ........ 0.0234 ...... 0.0080 ........ 0.0052
8-bit int #1 ......... 0.0076 ........ 0.0107 ...... 0.0081 ........ 0.0085
8-bit int #2 ......... 0.0077 ........ 0.0100 ...... 0.0155 ........ 0.0054
8-bit int #3 ......... 0.0077 ........ 0.0107 ...... 0.0079 ........ 0.0081
16-bit int #1 ........ 0.0105 ........ 0.0119 ...... 0.0082 ........ 0.0050
16-bit int #2 ........ 0.0103 ........ 0.0118 ...... 0.0081 ........ 0.0080
16-bit int #3 ........ 0.0108 ........ 0.0118 ...... 0.0108 ........ 0.0050
32-bit int #1 ........ 0.0112 ........ 0.0205 ...... 0.0116 ........ 0.0052
32-bit int #2 ........ 0.0109 ........ 0.0153 ...... 0.0078 ........ 0.0050
32-bit int #3 ........ 0.0112 ........ 0.0154 ...... 0.0082 ........ 0.0078
64-bit int #1 ........ 0.0118 ........ 0.0235 ...... 0.0153 ........ 0.0052
64-bit int #2 ........ 0.0117 ........ 0.0237 ...... 0.0080 ........ 0.0048
64-bit int #3 ........ 0.0117 ........ 0.0238 ...... 0.0080 ........ 0.0050
64-bit int #4 ........ 0.0119 ........ 0.0235 ...... 0.0082 ........ 0.0046
64-bit float #1 ...... 0.0108 ........ 0.0286 ...... 0.0145 ........ 0.0052
64-bit float #2 ...... 0.0107 ........ 0.0230 ...... 0.0076 ........ 0.0051
64-bit float #3 ...... 0.0108 ........ 0.0218 ...... 0.0076 ........ 0.0051
fix string #1 ........ 0.0019 ........ 0.0068 ...... 0.0084 ........ 0.0051
fix string #2 ........ 0.0070 ........ 0.0108 ...... 0.0085 ........ 0.0069
fix string #3 ........ 0.0071 ........ 0.0122 ...... 0.0088 ........ 0.0069
fix string #4 ........ 0.0106 ........ 0.0120 ...... 0.0084 ........ 0.0066
8-bit string #1 ...... 0.0104 ........ 0.0208 ...... 0.0122 ........ 0.0074
8-bit string #2 ...... 0.0108 ........ 0.0159 ...... 0.0086 ........ 0.0070
8-bit string #3 ...... 0.0111 ........ 0.0162 ...... 0.0165 ........ 0.0073
16-bit string #1 ..... 0.0141 ........ 0.0181 ...... 0.0144 ........ 0.0090
16-bit string #2 ..... 0.1550 ........ 0.1644 ...... 0.1534 ........ 0.1488
32-bit string ........ 0.1547 ........ 0.1591 ...... 0.1572 ........ 0.1561
wide char string #1 .. 0.0070 ........ 0.0118 ...... 0.0084 ........ 0.0070
wide char string #2 .. 0.0106 ........ 0.0161 ...... 0.0089 ........ 0.0112
8-bit binary #1 ........... I ............. I ........... F ............. I
8-bit binary #2 ........... I ............. I ........... F ............. I
8-bit binary #3 ........... I ............. I ........... F ............. I
16-bit binary ............. I ............. I ........... F ............. I
32-bit binary ............. I ............. I ........... F ............. I
fix array #1 ......... 0.0024 ........ 0.0075 ...... 0.0163 ........ 0.0063
fix array #2 ......... 0.0179 ........ 0.0198 ...... 0.0192 ........ 0.0176
fix array #3 ......... 0.0261 ........ 0.0340 ...... 0.0231 ........ 0.0269
16-bit array #1 ...... 0.0662 ........ 0.0546 ...... 0.0345 ........ 0.0391
16-bit array #2 ........... S ............. S ........... S ............. S
32-bit array .............. S ............. S ........... S ............. S
complex array ............. I ............. I ........... F ............. F
fix map #1 ................ I ............. I ........... F ............. I
fix map #2 ........... 0.0214 ........ 0.0306 ...... 0.0197 ........ 0.0207
fix map #3 ................ I ............. I ........... F ............. I
fix map #4 ........... 0.0292 ........ 0.0270 ...... 0.0306 ........ 0.0209
16-bit map #1 ........ 0.1031 ........ 0.0896 ...... 0.0378 ........ 0.0506
16-bit map #2 ............. S ............. S ........... S ............. S
32-bit map ................ S ............. S ........... S ............. S
complex map .......... 0.1164 ........ 0.1131 ...... 0.0591 ........ 0.0583
fixext 1 .................. I ............. I ........... F ............. F
fixext 2 .................. I ............. I ........... F ............. F
fixext 4 .................. I ............. I ........... F ............. F
fixext 8 .................. I ............. I ........... F ............. F
fixext 16 ................. I ............. I ........... F ............. F
8-bit ext ................. I ............. I ........... F ............. F
16-bit ext ................ I ............. I ........... F ............. F
32-bit ext ................ I ............. I ........... F ............. F
===========================================================================
Total                  1.1056          1.3705        0.9900          0.8214
Skipped                     4               4             4               4
Failed                      0               0            16               9
Ignored                    16              16             0               7
```
</details>

> *Note that the msgpack extension (v2.1.2) doesn't support **ext**, **bin** and UTF-8 **str** types.*


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
