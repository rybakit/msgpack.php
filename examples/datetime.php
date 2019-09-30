<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\DateTimeExtension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$date = (new \DateTimeImmutable('2019-01-16', new \DateTimeZone('Europe/Minsk')))
    ->setTime(22, 18, 22, 294418);

$extension = new DateTimeExtension(2);

$packer = new Packer(null, [$extension]);
$packed = $packer->pack($date);

$unpacker = new BufferUnpacker($packed, null, [$extension]);

printf("Raw:      %s\n", $date->format('Y-m-d\TH:i:s.uP'));
printf("Unpacked: %s\n", $unpacker->unpack()->format('Y-m-d\TH:i:s.uP'));

/* OUTPUT
Raw:      2019-01-16T22:18:22.294418+03:00
Unpacked: 2019-01-16T22:18:22.294418+03:00
*/
