<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MessagePack\Packer;
use MessagePack\PackOptions;
use MessagePack\Type\Binary;
use MessagePack\TypeTransformer\BinaryTransformer;

require __DIR__.'/autoload.php';

// https://stackoverflow.com/questions/40808984/msgpack-between-php-and-javascript

$packer = new Packer(PackOptions::FORCE_STR);
$packer->registerTransformer(new BinaryTransformer());

$packed = $packer->pack(['name' => new Binary('value')]);

echo '[', implode(', ', unpack('C*', $packed)), "]\n";

/* OUTPUT
[129, 164, 110, 97, 109, 101, 196, 5, 118, 97, 108, 117, 101]
*/
