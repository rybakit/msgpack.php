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

use PHPUnit\Framework\TestCase;

final class ExamplesTest extends TestCase
{
    /**
     * @dataProvider provideExampleData
     */
    public function testExample(string $filename) : void
    {
        exec("php $filename", $output, $exitCode);

        self::assertSame(0, $exitCode);

        if ($output) {
            self::assertOutput($filename, implode("\n", $output));
        }
    }

    public function provideExampleData() : iterable
    {
        $dir = dirname(__DIR__).'/examples';
        foreach (glob("$dir/*.php") as $filename) {
            if (strpos($filename, 'autoload.php')) {
                continue;
            }
            yield [$filename];
        }
    }

    private static function assertOutput(string $filename, string $output) : void
    {
        $content = file_get_contents($filename);

        if (preg_match('~\/\*\s*?OUTPUT\b(.+?)\*\/~s', $content, $matches)) {
            self::assertSame(trim($matches[1]), $output);
        }
    }
}
