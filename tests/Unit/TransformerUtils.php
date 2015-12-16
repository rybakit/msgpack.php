<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit;

use MessagePack\TypeTransformer\Collection;
use MessagePack\TypeTransformer\TypeTransformer;

trait TransformerUtils
{
    private function getTransformerMock($id)
    {
        $transformer = $this->getMock('MessagePack\TypeTransformer\TypeTransformer');
        $transformer->expects($this->any())->method('getId')
            ->willReturn($id);

        return $transformer;
    }

    /**
     * @param TypeTransformer[] $transformers
     *
     * @return Collection
     */
    private function getTransformerCollectionMock(array $transformers = null)
    {
        $coll = $this->getMock('MessagePack\TypeTransformer\Collection');

        if ($transformers) {
            $coll->expects($this->any())->method('find')
                ->willReturn($transformers[0]);

            $coll->expects($this->any())->method('match')
                ->willReturn($transformers[0]);
        }

        return $coll;
    }
}
