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

interface TypeTransformer
{
    /**
     * @return int
     */
    public function getId();

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function supports($value);

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function transform($value);

    /**
     * @param string $data
     *
     * @return mixed
     */
    public function reverseTransform($data);
}
