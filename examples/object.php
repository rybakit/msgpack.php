<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MessagePack\BufferUnpacker;
use MessagePack\Extension\ObjectExtension;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$ext = new ObjectExtension(2);
$packer = (new Packer())->extendWith($ext);
$unpacker = (new BufferUnpacker())->extendWith($ext);

$obj = (object) ['name' => 'Alice', 'age' => 30];
$packed = $packer->pack($obj);
$result = $unpacker->reset($packed)->unpack();

echo 'type: '.$result::class."\n";
echo "name: {$result->name}\n";
echo "age: {$result->age}\n";

// Nested objects are also round-tripped
$obj = json_decode('{"user": {"name": "Bob"}, "scores": [1, 2, 3]}');
$packed = $packer->pack($obj);
$result = $unpacker->reset($packed)->unpack();

echo 'user type: '.get_class($result->user)."\n";
echo "user.name: {$result->user->name}\n";
echo 'scores: '.implode(', ', $result->scores)."\n";

/* OUTPUT
type: stdClass
name: Alice
age: 30
user type: stdClass
user.name: Bob
scores: 1, 2, 3
*/
