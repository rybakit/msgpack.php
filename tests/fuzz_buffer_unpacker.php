<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MessagePack\BufferUnpacker;
use PhpFuzzer\Fuzzer;

require __DIR__.'/../vendor/autoload.php';

/* @var Fuzzer $fuzzer */
$fuzzer->setTarget(static function (string $input) {
    (new BufferUnpacker($input))->unpack();
});

$fuzzer->setMaxLen(1024);
