<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests;

use MessagePack\Packer;
use MessagePack\PackOptions;
use MessagePack\Tests\Perf\Benchmark\AverageableBenchmark;
use MessagePack\Tests\Perf\Benchmark\DurationBenchmark;
use MessagePack\Tests\Perf\Benchmark\FilterableBenchmark;
use MessagePack\Tests\Perf\Benchmark\IterationBenchmark;
use MessagePack\Tests\Perf\Filter\ListFilter;
use MessagePack\Tests\Perf\Filter\RegexFilter;
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

function resolve_filter($testNames)
{
    if ('/' === $testNames[0]) {
        return new RegexFilter($testNames);
    }

    if ('@' !== $testNames[0] && '@' !== $testNames[1]) {
        return new ListFilter(explode(',', $testNames));
    }

    $exclude = '-' === $testNames[0];

    switch (ltrim($testNames, '-@')) {
        case 'slow':
            return $exclude
                ? ListFilter::fromBlacklist(DataProvider::getSlowTestNames())
                : ListFilter::fromWhitelist(DataProvider::getSlowTestNames());

        case 'pecl_comp':
            return $exclude
                ? ListFilter::fromWhitelist(DataProvider::getPeclIncompatibleTestNames())
                : ListFilter::fromBlacklist(DataProvider::getPeclIncompatibleTestNames());
    }

    throw new \UnexpectedValueException(sprintf('Unknown test group "%s".', $testNames));
}

set_error_handler(function ($code, $message) { throw new \RuntimeException($message); });

$targetAliases = getenv('MP_BENCH_TARGETS') ?: 'pure_p,pure_bu';
$rounds = getenv('MP_BENCH_ROUNDS') ?: 3;
$testNames = getenv('MP_BENCH_TESTS') ?: '-@slow';

$benchmark = getenv('MP_BENCH_DURATION')
    ? new DurationBenchmark(getenv('MP_BENCH_DURATION'))
    : new IterationBenchmark(getenv('MP_BENCH_ITERATIONS') ?: 100000);

if ($rounds) {
    $benchmark = new AverageableBenchmark($benchmark, $rounds);
}
if ($testNames) {
    $filter = resolve_filter($testNames);
    $benchmark = new FilterableBenchmark($benchmark, $filter);
}

$targetFactories = [
    'pecl_p' => function () { return new PeclFunctionPackTarget(); },
    'pecl_u' => function () { return new PeclFunctionUnpackTarget(); },
    'pure_p' => function () { return new PackerTarget('Packer'); },
    'pure_ps' => function () { return new PackerTarget('Packer (force_str)', new Packer(PackOptions::FORCE_STR)); },
    'pure_pa' => function () { return new PackerTarget('Packer (force_arr)', new Packer(PackOptions::FORCE_ARR)); },
    'pure_psa' => function () { return new PackerTarget('Packer (force_str|force_arr)', new Packer(PackOptions::FORCE_STR | PackOptions::FORCE_ARR)); },
    'pure_bu' => function () { return new BufferUnpackerTarget('BufferUnpacker'); },
];

$targets = [];
foreach (explode(',', $targetAliases) as $alias) {
    $targets[] = $targetFactories[trim($alias)]();
}

$runner = new Runner(DataProvider::provideData());

gc_disable();
$runner->run($benchmark, $targets);
