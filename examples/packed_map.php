<?php

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
        'id' => $id = random_int(0, 999),
        'first_name' => sprintf('first_name_%03s', $id),
        'last_name' => sprintf('last_name_%03s', $id),
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
printf("Space savings:  %.2F%%\n", 1 - strlen($packedPackedMap) / strlen($packedMap));
