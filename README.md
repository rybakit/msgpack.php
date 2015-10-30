# msgpack.php

[![Build Status](https://travis-ci.org/rybakit/msgpack.php.svg?branch=master)](https://travis-ci.org/rybakit/msgpack.php)


## Installation

The recommended way to install the library is through [Composer](http://getcomposer.org):

```sh
$ composer require rybakit/msgpack
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
