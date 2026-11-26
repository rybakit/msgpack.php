<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\Extension\Fixtures;

class PrivatePropsDto
{
    public string $pub = 'public';
    protected string $prot = 'protected';
    private string $priv = 'private';

    public function getProt() : string
    {
        return $this->prot;
    }

    public function getPriv() : string
    {
        return $this->priv;
    }
}
