<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\StructuredMap;
use App\MessagePack\StructuredMapExtension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$profileSchema = [
    'id' => 'int',
    'first_name' => 'str',
    'last_name' => 'str',
];

$profiles = [];
for ($i = 0; $i < 1000; ++$i) {
    $profiles[] = [
        'id' => $i,
        'first_name' => sprintf('first_name_%03s', $i),
        'last_name' => sprintf('last_name_%03s', $i),
    ];
}

$extension = new StructuredMapExtension(3);
$packer = new Packer(null, [$extension]);
$unpacker = new BufferUnpacker('', null, [$extension]);

$packedMap = $packer->pack($profiles);
$packedStructuredMap = $packer->pack(new StructuredMap($profileSchema, $profiles));

$unpackedMap = $unpacker->reset($packedMap)->unpack();
$unpackedStructuredMap = $unpacker->reset($packedStructuredMap)->unpack();

if (($unpackedMap !== $profiles) || ($unpackedStructuredMap !== $profiles)) {
    exit(1);
}

printf("Map size:           %dB\n", strlen($packedMap));
printf("StructuredMap size: %dB\n", strlen($packedStructuredMap));
printf("Space savings:      %d%%\n", round(1 - strlen($packedStructuredMap) / strlen($packedMap), 2) * 100);

/* OUTPUT
Map size:           56619B
StructuredMap size: 31660B
Space savings:      44%
*/
