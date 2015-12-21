<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MessagePack\Tests\DataProvider;
use MessagePack\Tests\Perf\Benchmark\AverageableBenchmark;
use MessagePack\Tests\Perf\Benchmark\FilterableBenchmark;
use MessagePack\Tests\Perf\Benchmark\IterationBenchmark;
use MessagePack\Tests\Perf\Benchmark\TimeBenchmark;
use MessagePack\Tests\Perf\Filter\NameFilter;
use MessagePack\Tests\Perf\Runner;
use MessagePack\Tests\Perf\Target\BufferUnpackerTarget;
use MessagePack\Tests\Perf\Target\PackerTarget;
use MessagePack\Tests\Perf\Target\PeclFunctionPackTarget;
use MessagePack\Tests\Perf\Target\PeclFunctionUnpackTarget;

require __DIR__.'/../vendor/autoload.php';

if (extension_loaded('xdebug')) {
    echo "The benchmark must be run with xdebug extension disabled.\n";
    exit(42);
}

set_error_handler(function ($code, $message) { throw new \RuntimeException($message); });

$targetNames = getenv('MP_BENCH_TARGETS') ?: 'pure_p,pure_u';
$cycles = getenv('MP_BENCH_CYCLES') ?: 3;
$testNames = getenv('MP_BENCH_TESTS') ?: '-16-bit array #2, -32-bit array, -16-bit map #2, -32-bit map';
//$asJson = in_array(strtolower(getenv('MP_BENCH_AS_JSON')), ['1', 'true', 'on'], true);

$benchmark = getenv('MP_BENCH_TIME')
    ? new TimeBenchmark(getenv('MP_BENCH_TIME'))
    : new IterationBenchmark(getenv('MP_BENCH_SIZE') ?: 100000);

if ($cycles) {
    $benchmark = new AverageableBenchmark($benchmark, $cycles);
}
if ($testNames) {
    $benchmark = new FilterableBenchmark($benchmark, new NameFilter(explode(',', $testNames)));
}

$targetFactories = [
    'pecl_p' => function () { return new PeclFunctionPackTarget(); },
    'pecl_u' => function () { return new PeclFunctionUnpackTarget(); },
    'pure_p' => function () { return new PackerTarget(); },
    'pure_u' => function () { return new BufferUnpackerTarget(); },
];

$targets = [];
foreach (explode(',', $targetNames) as $targetName) {
    $targets[] = $targetFactories[trim($targetName)]();
}

$runner = new Runner(DataProvider::provideData());

gc_disable();
$runner->run($benchmark, $targets);
