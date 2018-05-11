<?php

// https://stackoverflow.com/questions/40808984/msgpack-between-php-and-javascript

use MessagePack\Packer;
use MessagePack\PackOptions;
use MessagePack\Type\Binary;
use MessagePack\TypeTransformer\BinaryTransformer;

require __DIR__.'/autoload.php';

$packer = new Packer(PackOptions::FORCE_STR);
$packer->registerTransformer(new BinaryTransformer());

$packed = $packer->pack(['name' => new Binary('value')]);

echo '[', implode(', ', unpack('C*', $packed)), "]\n";
