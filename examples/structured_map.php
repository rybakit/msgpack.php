<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\MessagePack\StructuredMap;
use App\MessagePack\StructuredMapExtension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

require __DIR__.'/autoload.php';

$profileSchema = [
    'id' => 'int',
    'first_name' => 'str',
    'last_name' => 'str',
];

$profiles = [];
for ($i = 0; $i < 1000; ++$i) {
    $profiles[] = [
        'id' => $i,
        'first_name' => "first_name_$i",
        'last_name' => "last_name_$i",
    ];
}

$extension = new StructuredMapExtension(3);
$packer = new Packer(null, [$extension]);
$unpacker = new BufferUnpacker('', null, [$extension]);

$packedMap = $packer->pack($profiles);
$packedStructuredMap = $packer->pack(new StructuredMap($profileSchema, $profiles));

$unpackedMap = $unpacker->reset($packedMap)->unpack();
$unpackedStructuredMap = $unpacker->reset($packedStructuredMap)->unpack();

if (($unpackedMap !== $profiles) || ($unpackedStructuredMap !== $profiles)) {
    exit(1);
}

printf("Packed map size:            %dB\n", strlen($packedMap));
printf("Packed structured map size: %dB\n", strlen($packedStructuredMap));
printf("Space saved:                %dB\n", strlen($packedMap) - strlen($packedStructuredMap));
printf("Percentage saved:           %d%%\n", round(1 - strlen($packedStructuredMap) / strlen($packedMap), 2) * 100);

/* OUTPUT
Packed map size:            56399B
Packed structured map size: 31440B
Space saved:                24959B
Percentage saved:           44%
*/
