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

use MessagePack\Tests\Unit\TransformerUtils;
use MessagePack\TypeTransformer\Collection;

class CollectionTest extends \PHPUnit_Framework_TestCase
{
    use TransformerUtils;

    /**
     * @var Collection
     */
    private $coll;

    protected function setUp()
    {
        $this->coll = new Collection();
    }

    public function testConstructor()
    {
        $this->assertNull($this->coll->find(5));

        $t1 = $this->getTransformerMock(1);
        $t2 = $this->getTransformerMock(2);

        $coll = new Collection([$t1, $t2]);
        $this->assertSame($t2, $coll->find(2));
    }

    public function testAddRemoveFind()
    {
        $id = 5;
        $transformer = $this->getTransformerMock($id);

        $this->coll->add($transformer);
        $this->assertSame($transformer, $this->coll->find($id));

        $this->coll->remove($id);
        $this->assertNull($this->coll->find($id));
    }

    public function testMatchReturnsTransformer()
    {
        $value = new \stdClass();

        $t1 = $this->getTransformerMock(1);
        $t1->expects($this->once())->method('supports')
            ->with($value)
            ->willReturn(false);
        $this->coll->add($t1);

        $t2 = $this->getTransformerMock(2);
        $t2->expects($this->once())->method('supports')
            ->with($value)
            ->willReturn(true);
        $this->coll->add($t2);

        $t3 = $this->getTransformerMock(3);
        $t3->expects($this->exactly(0))->method('supports');
        $this->coll->add($t3);

        $this->assertSame($t2, $this->coll->match($value));
    }

    public function testMatchReturnsNull()
    {
        $t1 = $this->getTransformerMock(1);
        $t1->expects($this->once())->method('supports')->willReturn(false);
        $this->coll->add($t1);

        $t2 = $this->getTransformerMock(2);
        $t2->expects($this->once())->method('supports')->willReturn(false);
        $this->coll->add($t2);

        $this->assertNull($this->coll->match(new \stdClass()));
    }
}
