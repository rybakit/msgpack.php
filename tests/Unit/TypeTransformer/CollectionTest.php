<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\TypeTransformer;

use MessagePack\TypeTransformer\Collection;

class CollectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Collection
     */
    private $collection;

    protected function setUp()
    {
        $this->collection = new Collection();
    }

    public function testAddRemoveFind()
    {
        $id = 5;
        $transformer = $this->getMock('MessagePack\TypeTransformer\TypeTransformer');
        $transformer->expects($this->once())->method('getId')->willReturn($id);

        $this->collection->add($transformer);
        $this->assertSame($transformer, $this->collection->find($id));

        $this->collection->remove($id);
        $this->assertNull($this->collection->find($id));
    }

    public function testMatchReturnsTransformer()
    {
        $value = new \stdClass();

        $t1 = $this->getTransformerMock(1);
        $t1->expects($this->once())->method('supports')
            ->with($value)
            ->willReturn(false);
        $this->collection->add($t1);

        $t2 = $this->getTransformerMock(2);
        $t2->expects($this->once())->method('supports')
            ->with($value)
            ->willReturn(true);
        $this->collection->add($t2);

        $t3 = $this->getTransformerMock(3);
        $t3->expects($this->exactly(0))->method('supports');
        $this->collection->add($t3);

        $this->assertSame($t2, $this->collection->match($value));
    }

    public function testMatchReturnsNull()
    {
        $t1 = $this->getTransformerMock(1);
        $t1->expects($this->once())->method('supports')->willReturn(false);
        $this->collection->add($t1);

        $t2 = $this->getTransformerMock(2);
        $t2->expects($this->once())->method('supports')->willReturn(false);
        $this->collection->add($t2);

        $this->assertNull($this->collection->match(new \stdClass()));
    }

    private function getTransformerMock($id)
    {
        $transformer = $this->getMock('MessagePack\TypeTransformer\TypeTransformer');
        $transformer->expects($this->any())->method('getId')
            ->willReturn($id);

        return $transformer;
    }
}
