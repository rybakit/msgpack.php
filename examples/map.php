<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MessagePack\Packer;
use MessagePack\Type\Map;

require __DIR__.'/autoload.php';

$packer = new Packer();

$packedMap = $packer->pack(new Map([1, 2, 3]));
$packedArray = $packer->pack([1, 2, 3]);

printf("Packed map:   %s\n", implode(' ', str_split(bin2hex($packedMap), 2)));
printf("Packed array: %s\n", implode(' ', str_split(bin2hex($packedArray), 2)));

/* OUTPUT
Packed map:   83 00 01 01 02 02 03
Packed array: 93 01 02 03
*/
