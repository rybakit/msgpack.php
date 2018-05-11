<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Perf\Filter;

use MessagePack\Tests\Perf\Test;

class ListFilter implements Filter
{
    private $whitelist = [];
    private $blacklist = [];

    public function __construct(array $list)
    {
        foreach ($list as $name) {
            $name = trim($name);

            if ('-' !== $name[0]) {
                $this->whitelist[] = $name;
                continue;
            }

            $this->blacklist[] = substr($name, 1);
        }
    }

    public static function fromBlacklist(array $blacklist) : self
    {
        $self = new self([]);
        $self->blacklist = $blacklist;

        return $self;
    }

    public static function fromWhitelist(array $whitelist) : self
    {
        $self = new self([]);
        $self->whitelist = $whitelist;

        return $self;
    }

    public function reset() : void
    {
        $this->whitelist = [];
        $this->blacklist = [];
    }

    public function isAccepted(Test $test) : bool
    {
        if (in_array($test->getName(), $this->blacklist, true)) {
            return false;
        }

        if ($this->whitelist && !in_array($test->getName(), $this->whitelist, true)) {
            return false;
        }

        return true;
    }
}
