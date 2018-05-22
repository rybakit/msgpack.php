<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\Uint64;
use App\MessagePack\Uint64Transformer;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

if (!extension_loaded('gmp')) {
    echo "GMP extension is required to run this example.\n";
    exit(1);
}

$packer = new Packer();
$packer->registerTransformer(new Uint64Transformer());

$uint64 = new Uint64('18446744073709551615');
$packed = $packer->pack($uint64);

printf("Packed (%s): %s\n", $uint64, bin2hex($packed));
printf("Unpacked: %s\n", (new BufferUnpacker($packed))->unpack());

/* OUTPUT
Packed (18446744073709551615): cfffffffffffffffff
Unpacked: 18446744073709551615
*/
