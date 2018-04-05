<?php

namespace App\MessagePack;

final class PackedMap
{
    public $map;
    public $schema;

    public function __construct(array $map, array $schema)
    {
        $this->map = $map;
        $this->schema = $schema;
    }
}
