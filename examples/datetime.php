<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\DateTimeTransformer;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$date = new DateTimeImmutable('2000-01-01');
$transformer = new DateTimeTransformer(2);

$packer = new Packer();
$packer->registerTransformer($transformer);
$packed = $packer->pack($date);

$unpacker = new BufferUnpacker($packed);
$unpacker->registerTransformer($transformer);

printf("Raw:      %s\n", $date->format('r'));
printf("Unpacked: %s\n", $unpacker->unpack()->format('r'));

/* OUTPUT
Raw:      Sat, 01 Jan 2000 00:00:00 +0000
Unpacked: Sat, 01 Jan 2000 00:00:00 +0000
*/
