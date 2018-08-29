<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\PackedMap;
use App\MessagePack\PackedMapTransformer;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$schema = [
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

$transformer = new PackedMapTransformer(3);

$packer = new Packer();
$packer->registerTransformer($transformer);

$unpacker = new BufferUnpacker();
$unpacker->registerTransformer($transformer);

$packedMap = $packer->pack($profiles);
$packedPackedMap = $packer->pack(new PackedMap($profiles, $schema));

$unpackedMap = $unpacker->reset($packedMap)->unpack();
$unpackedPackedMap = $unpacker->reset($packedPackedMap)->unpack();

if (($unpackedMap !== $profiles) || ($unpackedPackedMap !== $profiles)) {
    exit(1);
}

printf("Map size:       %dB\n", strlen($packedMap));
printf("PackedMap size: %dB\n", strlen($packedPackedMap));
printf("Space savings:  %d%%\n", round(1 - strlen($packedPackedMap) / strlen($packedMap), 2) * 100);

/* OUTPUT
Map size:       56619B
PackedMap size: 31660B
Space savings:  44%
*/
