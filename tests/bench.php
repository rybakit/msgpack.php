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
use MessagePack\Tests\Perf\Benchmark\DurationBenchmark;
use MessagePack\Tests\Perf\Benchmark\FilterableBenchmark;
use MessagePack\Tests\Perf\Benchmark\IterationBenchmark;
use MessagePack\Tests\Perf\Filter\NameFilter;
use MessagePack\Tests\Perf\Filter\RegexpFilter;
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
$rounds = getenv('MP_BENCH_ROUNDS') ?: 3;
$testNames = getenv('MP_BENCH_TESTS') ?: '-16-bit array #2, -32-bit array, -16-bit map #2, -32-bit map';

$benchmark = getenv('MP_BENCH_DURATION')
    ? new DurationBenchmark(getenv('MP_BENCH_DURATION'))
    : new IterationBenchmark(getenv('MP_BENCH_ITERATIONS') ?: 100000);

if ($rounds) {
    $benchmark = new AverageableBenchmark($benchmark, $rounds);
}
if ($testNames) {
    $filter = '/' === $testNames[0] ? new RegexpFilter($testNames) : new NameFilter(explode(',', $testNames));
    $benchmark = new FilterableBenchmark($benchmark, $filter);
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
