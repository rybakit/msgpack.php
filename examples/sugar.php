<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MessagePack\MessagePack;

require __DIR__.'/autoload.php';

$packed = MessagePack::pack('foobar');
$unpacked = MessagePack::unpack($packed);

echo bin2hex($packed)."\n";
echo "$unpacked\n";

/* OUTPUT
a6666f6f626172
foobar
*/
