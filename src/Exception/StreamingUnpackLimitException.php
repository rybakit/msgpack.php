<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Exception;

final class StreamingUnpackLimitException extends UnpackingFailedException
{
    public function __construct(private readonly int $maxRetries)
    {
        parent::__construct(\sprintf('Incomplete message exceeded the streaming unpack retry limit of %d', $maxRetries));
    }

    public function getMaxRetries() : int
    {
        return $this->maxRetries;
    }
}
