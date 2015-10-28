<?php

use MessagePack\Tests\Benchmark\Benchmark;
use MessagePack\Tests\Benchmark\Packing;
use MessagePack\Tests\Benchmark\Unpacking;
use MessagePack\Tests\DataProvider;

require __DIR__.'/../vendor/autoload.php';

if (function_exists('xdebug_break')) {
    echo "The benchmark must be run with Xdebug extension disabled.\n";
    exit(42);
}

function run(Benchmark $benchmark)
{
    echo $benchmark->getTitle()."\n";
    echo str_repeat('=', 28)."\n";
    echo str_pad('Test', 19, ' ')."Time, sec\n";
    echo str_repeat('-', 28)."\n";

    $totalTime = 0;
    foreach (DataProvider::provideData() as $set) {
        $totalTime += $time = $benchmark->measure($set[1], $set[2]);

        echo str_pad($set[0], 22, ' ');
        printf("%.4f\n", $time);
    }

    echo str_repeat('-', 28)."\n";
    echo str_repeat(' ', 15).sprintf("Total: %.4f\n\n", $totalTime);
}

$size = 1000;
$benchmark = 'p';

foreach (array_slice($argv, 1) as $opt) {
    if ('--size=' === substr($opt, 0, 7)) {
        $size = (int) substr($opt, 7);
    } else if ('--benchmark=' === substr($opt, 0, 12)) {
        $benchmark = substr($opt, 12);
    }
}

if (!in_array($benchmark, ['p', 'u'], true)) {
    echo "Invalid benchmark name, use 'p' or 'u'.\n";
    exit(43);
}

run('p' === $benchmark ? new Packing($size) : new Unpacking($size));
