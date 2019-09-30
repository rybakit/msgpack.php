<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\ArrayIteratorExtension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$extension = new ArrayIteratorExtension(1);

$packer = new Packer(null, [$extension]);
$packed = $packer->pack(new ArrayIterator(range(1, 10000)));

$unpacker = new BufferUnpacker($packed, null, [$extension]);

$sum = 0;
foreach ($unpacker->unpack() as $i) {
    $sum += $i;
}

echo "Sum: $sum\n";

/* OUTPUT
Sum: 50005000
*/
