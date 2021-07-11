<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\Uint64;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\UnpackOptions;

require __DIR__.'/autoload.php';

if (!extension_loaded('gmp')) {
    echo "GMP extension is required to run this example.\n";
    exit(1);
}

$packer = new Packer();

$uint64 = new Uint64('18446744073709551615');
$packed = $packer->pack($uint64);

printf("Packed (%s): %s\n", (string) $uint64, bin2hex($packed));
printf("Unpacked: %s\n", (new BufferUnpacker($packed, UnpackOptions::BIGINT_AS_STR))->unpack());

/* OUTPUT
Packed (18446744073709551615): cfffffffffffffffff
Unpacked: 18446744073709551615
*/
