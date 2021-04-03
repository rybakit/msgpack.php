<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\StructList;
use App\MessagePack\StructListExtension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$profiles = [];
for ($i = 1; $i <= 100; ++$i) {
    $profiles[] = [
        'id' => $i,
        'first_name' => "First name $i",
        'last_name' => "Last name $i",
    ];
}

$extension = new StructListExtension(3);
$packer = new Packer(null, [$extension]);
$unpacker = new BufferUnpacker('', null, [$extension]);

$packedList = $packer->pack($profiles);
$packedStructList = $packer->pack(new StructList($profiles));

$unpackedList = $unpacker->reset($packedList)->unpack();
$unpackedStructList = $unpacker->reset($packedStructList)->unpack();

if (($unpackedList !== $profiles) || ($unpackedStructList !== $profiles)) {
    exit(1);
}

printf("Packed list size:        %dB\n", strlen($packedList));
printf("Packed struct list size: %dB\n", strlen($packedStructList));
printf("Space saved:             %dB\n", strlen($packedList) - strlen($packedStructList));
printf("Percentage saved:        %d%%\n", round(1 - strlen($packedStructList) / strlen($packedList), 2) * 100);

/* OUTPUT
Packed list size:        5287B
Packed struct list size: 2816B
Space saved:             2471B
Percentage saved:        47%
*/
