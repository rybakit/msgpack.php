<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MessagePack\Tests\Perf\BenchmarkFactory;
use MessagePack\Tests\Perf\Filter\ListFilter;
use MessagePack\Tests\Perf\Runner;
use MessagePack\Tests\DataProvider;
use MessagePack\Tests\Perf\Writer\JsonWriter;

require __DIR__.'/../vendor/autoload.php';

if (function_exists('xdebug_break')) {
    echo "The benchmark must be run with Xdebug extension disabled.\n";
    exit(42);
}

$target = getenv('MP_BENCH_TARGET') ?: BenchmarkFactory::PURE_U;
$size = getenv('MP_BENCH_SIZE') ?: 100000;
$tests = getenv('MP_BENCH_TESTS') ?: '-16-bit array #2, -32-bit array, -16-bit map #2, -32-bit map';
$asJson = in_array(strtolower(getenv('MP_BENCH_AS_JSON')), ['1', 'true', 'on'], true);

$runner = new Runner(DataProvider::provideData(), $asJson ? new JsonWriter() : null);

if ($tests) {
    $runner->addFilter(new ListFilter(explode(',', $tests)));
}

$runner->run(BenchmarkFactory::create($target, $size));
