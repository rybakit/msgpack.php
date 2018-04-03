<?php

use App\MessagePack\DateTimeTransformer;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$date = new DateTime();
$transformer = new DateTimeTransformer(2);

$packer = new Packer();
$packer->registerTransformer($transformer);
$packed = $packer->pack($date);

$unpacker = new BufferUnpacker($packed);
$unpacker->registerTransformer($transformer);

\printf("Raw:      %s\n", $date->format('r'));
\printf("Unpacked: %s\n", $unpacker->unpack()->format('r'));
