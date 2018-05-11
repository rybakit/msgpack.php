<?php

use App\MessagePack\ArrayIteratorTransformer;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$transformer = new ArrayIteratorTransformer(1);

$packer = new Packer();
$packer->registerTransformer($transformer);
$packed = $packer->pack(new ArrayIterator(range(1, 10000)));

$unpacker = new BufferUnpacker($packed);
$unpacker->registerTransformer($transformer);

foreach ($unpacker->unpack() as $i) {
    echo "$i\n";
}
