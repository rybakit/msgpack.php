<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\TypeTransformer;

class Collection
{
    /**
     * @var TypeTransformer[]
     */
    private $items = [];

    /**
     * @param array|null $items
     */
    public function __construct(array $items = null)
    {
        foreach ((array) $items as $item) {
            $this->add($item);
        }
    }

    /**
     * @param TypeTransformer $transformer
     */
    public function add(TypeTransformer $transformer)
    {
        $this->items[$transformer->getId()] = $transformer;
    }

    /**
     * @param int $id
     */
    public function remove($id)
    {
        unset($this->items[$id]);
    }

    /**
     * @param int $id
     *
     * @return TypeTransformer|null
     */
    public function find($id)
    {
        if (isset($this->items[$id])) {
            return $this->items[$id];
        }
    }

    /**
     * @param mixed $value
     *
     * @return TypeTransformer|null
     */
    public function match($value)
    {
        foreach ($this->items as $item) {
            if ($item->supports($value)) {
                return $item;
            }
        }
    }
}
