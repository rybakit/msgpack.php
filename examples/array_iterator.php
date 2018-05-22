<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

$sum = 0;
foreach ($unpacker->unpack() as $i) {
    $sum += $i;
}

echo "Sum: $sum\n";

/* OUTPUT
Sum: 50005000
*/
