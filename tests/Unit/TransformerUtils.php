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
        $transformer = $this->getMockBuilder('MessagePack\TypeTransformer\TypeTransformer')->getMock();
        $transformer->expects(self::any())->method('getId')
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
        $coll = $this->getMockBuilder('MessagePack\TypeTransformer\Collection')->getMock();

        if ($transformers) {
            $coll->expects(self::any())->method('find')
                ->willReturn($transformers[0]);

            $coll->expects(self::any())->method('match')
                ->willReturn($transformers[0]);
        }

        return $coll;
    }
}
