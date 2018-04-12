<?php

use MessagePack\MessagePack;

require __DIR__.'/autoload.php';

$packed = MessagePack::pack('foobar');
$unpacked = MessagePack::unpack($packed);

echo "$unpacked\n";
