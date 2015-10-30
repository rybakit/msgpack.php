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

function run(Benchmark $benchmark, $testName = null, $tableLen = 32)
{
    echo str_repeat('=', $tableLen)."\n";
    printf("Type: %s\n", $benchmark->getTitle());
    printf("Size: %s\n", $benchmark->getSize());
    echo str_repeat('=', $tableLen)."\n";

    printf("Test %s Time, sec\n", str_repeat(' ', $tableLen - 15));
    echo str_repeat('-', $tableLen)."\n";

    $totalTime = 0;
    foreach (DataProvider::provideData() as $set) {
        if (null !== $testName && $set[0] !== $testName) {
            continue;
        }

        echo $set[0];

        $totalTime += $time = $benchmark->measure($set[1], $set[2]);
        $printTime = sprintf('%.4f', $time);

        printf(" %s %s\n",
            str_repeat('.', $tableLen - strlen($set[0]) - strlen($printTime) - 2),
            $printTime
        );
    }

    $summary = sprintf('Total: %.4f', $totalTime);

    echo str_repeat('-', $tableLen)."\n";
    echo str_repeat(' ', $tableLen - strlen($summary)).$summary."\n\n";
}

$size = getenv('MP_BENCH_SIZE') ?: 1000;
$bench = getenv('MP_BENCH_TYPE') ?: 'p';
$test = getenv('MP_BENCH_TEST') ?: null;

if (!in_array($bench, ['p', 'u'], true)) {
    echo "Invalid benchmark type, use 'p' or 'u'.\n";
    exit(43);
}

run('p' === $bench ? new Packing($size) : new Unpacking($size), $test);
