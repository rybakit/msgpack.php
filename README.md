# msgpack.php

[![Build Status](https://travis-ci.org/rybakit/msgpack.php.svg?branch=master)](https://travis-ci.org/rybakit/msgpack.php)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/rybakit/msgpack.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rybakit/msgpack.php/?branch=master)


## Installation

The recommended way to install the library is through [Composer](http://getcomposer.org):

```sh
$ composer require rybakit/msgpack
```


## Usage

### Packer

```php
use MessagePack\Packer;

$packed = Packer::pack('foo');
$packed = Packer::packArr([1, 2, 3]);
$packed = Packer::packMap([1, 2, 3]);
$packed = Packer::packInt(42);
$packed = Packer::packFloat(4.2);
$packed = Packer::packStr('foo');
$packed = Packer::packBin("\xf0\xf1");
$packed = Packer::packExt(new Ext(42, "\xf0\xf1"));
$packed = Packer::packNil();

$packed = Packer::packArr([1, 2, 3], [Packer::FORCE_STR => true, Packer::FORCE_MAP => true]);
$packed = Packer::packArr([1, 2, 3], Packer::FORCE_STR | Packer::FORCE_MAP);
$packed = Packer::packArr([1, 2, 3], assoc=false);
```

### Unpacker

```php
use MessagePack\Unpacker;

$unpacker = new Unpacker($packed);
$result = $unpacker->unpack();

$result = Unpacker::unpack($packed);
```

#### Streaming unpacking

```php
use MessagePack\Unpacker;

$unpacker = new Unpacker();
$result = $unpacker->tryUnpack(); // []
$unpacker->append("\xXX");
$result = $unpacker->tryUnpack(); // []
$unpacker->append("\xXX");
$result = $unpacker->tryUnpack(); // ['a']
```


## Tests

The easiest way to run tests is with Docker. First, build an image using the [dockerfile.sh](dockerfile.sh) generator:

```sh
$ ./dockerfile.sh | docker build -t msgpack -
```

Then run unit tests:

```sh
$ docker run --rm --name msgpack -v $(pwd):/msgpack -w /msgpack msgpack
```


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
