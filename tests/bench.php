<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MessagePack\Tests\Perf\Benchmark\AverageableBenchmark;
use MessagePack\Tests\Perf\Benchmark\FilterableBenchmark;
use MessagePack\Tests\Perf\Benchmark\LoopBenchmark;
use MessagePack\Tests\Perf\Filter\NameFilter;
use MessagePack\Tests\Perf\Runner;
use MessagePack\Tests\DataProvider;
use MessagePack\Tests\Perf\Target\TargetFactory;

require __DIR__.'/../vendor/autoload.php';

if (function_exists('xdebug_break')) {
    echo "The benchmark must be run with Xdebug extension disabled.\n";
    exit(42);
}

$target = getenv('MP_BENCH_TARGET') ?: TargetFactory::PURE_U;
$size = getenv('MP_BENCH_SIZE') ?: 100000;
$cycles = getenv('MP_BENCH_CYCLES') ?: 3;
$tests = getenv('MP_BENCH_TESTS') ?: '-16-bit array #2, -32-bit array, -16-bit map #2, -32-bit map';
//$asJson = in_array(strtolower(getenv('MP_BENCH_AS_JSON')), ['1', 'true', 'on'], true);

$target = TargetFactory::create($target);
$benchmark = new LoopBenchmark($size);

if ($cycles) {
    $benchmark = new AverageableBenchmark($benchmark, $cycles);
}
if ($tests) {
    $benchmark = new FilterableBenchmark($benchmark, new NameFilter(explode(',', $tests)));
}

$runner = new Runner(DataProvider::provideData());
$runner->run($benchmark, [$target]);
