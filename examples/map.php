<?php

use MessagePack\Packer;
use MessagePack\PackOptions;
use MessagePack\Type\Map;
use MessagePack\TypeTransformer\MapTransformer;

require __DIR__.'/autoload.php';

$packer = new Packer(PackOptions::FORCE_ARR);
$packer->registerTransformer(new MapTransformer());

$packed = $packer->pack([1, new Map([1, 2, 3]), 2]);

echo  \implode(' ', \str_split(\bin2hex($packed), 2))."\n";
