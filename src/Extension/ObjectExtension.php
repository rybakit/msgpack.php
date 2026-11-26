<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Extension;

use MessagePack\BufferUnpacker;
use MessagePack\Exception\PackingFailedException;
use MessagePack\Exception\UnpackingFailedException;
use MessagePack\Extension;
use MessagePack\Packer;

/**
 * An extension that enables round-trip packing and unpacking of arbitrary
 * PHP objects using a msgpack ext type.
 *
 * On pack:
 *   - If the object defines __serialize(), its return value is used as data
 *   - Otherwise, get_object_vars() is used (public properties only)
 *   - The class name (empty string for stdClass) and data are stored together
 *     inside the ext payload
 *
 * On unpack:
 *   - If the class defines __unserialize(), it is called with the stored data
 *   - Otherwise, properties are assigned directly
 *   - If the class is not in the allowed list or doesn't exist, an
 *     UnpackingFailedException is thrown
 *
 * Security:
 *   By default, only stdClass is allowed when unpacking. Pass an explicit
 *   allowlist of class/enum names as the second constructor argument, or use
 *   ObjectExtension::allowAll() for trusted payloads only.
 *
 * Usage:
 *   $ext = new ObjectExtension(2, [MyDto::class]);
 *   $packer = (new Packer())->extendWith($ext);
 *   $unpacker = (new BufferUnpacker())->extendWith($ext);
 *
 *   $packed = $packer->pack(new MyDto('Alice', 30));
 *   $result = $unpacker->reset($packed)->unpack();
 *   // $result instanceof MyDto
 */
final class ObjectExtension implements Extension
{
    private const PACK_UNSUPPORTED = 0;
    private const PACK_PROPS = 1;
    private const PACK_SERIALIZE = 2;

    /** @var array<string, int>|null */
    private ?array $allowedClasses;

    /** @var array<string, self::PACK_*> */
    private array $packCache = [];

    /** @var array<string, array{\ReflectionClass<object>, ?\Closure(object, array): void}> */
    private array $unpackCache = [];

    /** @var array<string, array<string, \UnitEnum>> */
    private array $enumCache = [];

    /**
     * @param int $type ext type ID (0-127)
     * @param list<string> $allowedClasses Allowed classes/enums (default [] = stdClass only)
     */
    public function __construct(
        private readonly int $type,
        array $allowedClasses = [],
    ) {
        if ($type < 0 || $type > 127) {
            throw new \InvalidArgumentException(\sprintf('Extension type must be between 0 and 127, %d given', $type));
        }

        $this->allowedClasses = \array_flip($allowedClasses);
    }

    public static function allowAll(int $type) : self
    {
        $ext = new self($type);
        $ext->allowedClasses = null;

        return $ext;
    }

    #[\Override]
    public function getType() : int
    {
        return $this->type;
    }

    /**
     * @param mixed $value
     */
    #[\Override]
    public function pack(Packer $packer, $value) : ?string
    {
        if (!\is_object($value)) {
            return null;
        }

        if ($value instanceof \UnitEnum) {
            return $packer->packExt($this->type,
                $packer->packStr($value::class).$packer->packStr($value->name),
            );
        }

        if ($value instanceof \stdClass) {
            return $packer->packExt($this->type,
                $packer->packStr('').$packer->packMap(\get_object_vars($value)),
            );
        }

        $className = $value::class;
        $mode = $this->packCache[$className] ??= $this->resolvePackMode($value);

        if (self::PACK_UNSUPPORTED === $mode) {
            throw PackingFailedException::unsupportedType($value);
        }

        $data = self::PACK_SERIALIZE === $mode ? $value->__serialize() : \get_object_vars($value);

        return $packer->packExt($this->type,
            $packer->packStr($className).$packer->packMap($data),
        );
    }

    /**
     * @return object
     */
    #[\Override]
    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        $className = $unpacker->unpackStr();

        if ('' === $className || 'stdClass' === $className) {
            return (object) $unpacker->unpackMap();
        }

        if (null !== $this->allowedClasses && !isset($this->allowedClasses[$className])) {
            throw new UnpackingFailedException(\sprintf('Class "%s" is not in the allowed classes list', $className));
        }

        if (isset($this->enumCache[$className])) {
            return $this->unpackEnum($className, $unpacker->unpackStr());
        }

        if (!isset($this->unpackCache[$className])) {
            if (\enum_exists($className)) {
                $this->enumCache[$className] = \array_column($className::cases(), null, 'name');

                return $this->unpackEnum($className, $unpacker->unpackStr());
            }

            if (!\class_exists($className)) {
                throw new UnpackingFailedException(\sprintf('Class "%s" does not exist', $className));
            }

            $ref = new \ReflectionClass($className);
            $hydrator = \method_exists($className, '__unserialize') ? null : \Closure::bind(
                static function (object $obj, array $data) : void {
                    foreach ($data as $key => $val) {
                        $obj->{$key} = $val;
                    }
                },
                null,
                $className,
            );

            $this->unpackCache[$className] = [$ref, $hydrator];
        }

        [$ref, $hydrator] = $this->unpackCache[$className];
        $data = $unpacker->unpackMap();
        $obj = $ref->newInstanceWithoutConstructor();

        if (null === $hydrator) {
            $obj->__unserialize($data);
        } else {
            $hydrator($obj, $data);
        }

        return $obj;
    }

    /**
     * @return self::PACK_*
     */
    private function resolvePackMode(object $value) : int
    {
        if ($value instanceof \Closure || $value instanceof \Generator) {
            return self::PACK_UNSUPPORTED;
        }

        $ref = new \ReflectionClass($value);
        if ($ref->isAnonymous() || !$ref->isInstantiable()) {
            return self::PACK_UNSUPPORTED;
        }

        return \method_exists($value, '__serialize')
            ? self::PACK_SERIALIZE
            : self::PACK_PROPS;
    }

    private function unpackEnum(string $className, string $caseName) : \UnitEnum
    {
        return $this->enumCache[$className][$caseName] ??
            throw new UnpackingFailedException(\sprintf('Enum case "%s::%s" does not exist', $className, $caseName));
    }
}
