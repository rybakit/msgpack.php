<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\Text;
use App\MessagePack\TextExtension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$extension = new TextExtension(3);
$packer = new Packer(null, [$extension]);
$unpacker = new BufferUnpacker('', null, [$extension]);

$longString = <<<STR
Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod
tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat
non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
STR;

$packedString = $packer->pack($longString);
$packedCompressedString = $packer->pack(new Text($longString));

$unpackedString = $unpacker->reset($packedString)->unpack();
$unpackedCompressedString = $unpacker->reset($packedCompressedString)->unpack();

if (($unpackedString !== $longString) || ($unpackedCompressedString !== $longString)) {
    exit(1);
}

printf("Packed string size: %dB\n", strlen($packedString));
printf("Packed text size:   %dB\n", strlen($packedCompressedString));
printf("Space saved:        %dB\n", strlen($packedString) - strlen($packedCompressedString));
printf("Percentage saved:   %d%%\n", round(1 - strlen($packedCompressedString) / strlen($packedString), 2) * 100);

/* OUTPUT
Packed string size: 448B
Packed text size:   294B
Space saved:        154B
Percentage saved:   34%
*/
