<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\TraversableExtension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$extension = new TraversableExtension(1);

$packer = new Packer(null, [$extension]);
$packed = $packer->pack(new ArrayIterator(range(1, 5)));

$unpacker = new BufferUnpacker($packed, null, [$extension]);

foreach ($unpacker->unpack() as $i) {
    echo "$i\n";
}

/* OUTPUT
1
2
3
4
5
*/
