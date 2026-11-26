<?php

/**
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\Extension;

use MessagePack\BufferUnpacker;
use MessagePack\Exception\PackingFailedException;
use MessagePack\Exception\UnpackingFailedException;
use MessagePack\Extension\ObjectExtension;
use MessagePack\Packer;
use MessagePack\Tests\Unit\Extension\Fixtures\PrivatePropsDto;
use MessagePack\Tests\Unit\Extension\Fixtures\ReadonlyAndUninitializedDto;
use MessagePack\Tests\Unit\Extension\Fixtures\SerializableDto;
use MessagePack\Tests\Unit\Extension\Fixtures\SimpleDto;
use MessagePack\Tests\Unit\Extension\Fixtures\StringBackedEnum;
use MessagePack\Tests\Unit\Extension\Fixtures\UnbackedEnum;
use PHPUnit\Framework\TestCase;

final class ObjectExtensionTest extends TestCase
{
    private const EXT_TYPE = 2;

    private Packer $packer;
    private BufferUnpacker $unpacker;

    protected function setUp() : void
    {
        $ext = ObjectExtension::allowAll(self::EXT_TYPE);
        $this->packer = (new Packer())->extendWith($ext);
        $this->unpacker = (new BufferUnpacker())->extendWith($ext);
    }

    public function testRoundTripStdClass() : void
    {
        $obj = (object) ['name' => 'Alice', 'age' => 30];

        $result = $this->packAndUnpack($obj);

        self::assertInstanceOf(\stdClass::class, $result);
        self::assertSame('Alice', $result->name);
        self::assertSame(30, $result->age);
    }

    public function testRoundTripEmptyStdClass() : void
    {
        $obj = new \stdClass();

        $result = $this->packAndUnpack($obj);

        self::assertInstanceOf(\stdClass::class, $result);
        self::assertNotSame($obj, $result);
        self::assertEquals($obj, $result);
    }

    public function testRoundTripNestedStdClass() : void
    {
        $obj = (object) [
            'user' => (object) ['name' => 'Bob'],
            'scores' => [1, 2, 3],
        ];

        $result = $this->packAndUnpack($obj);

        self::assertInstanceOf(\stdClass::class, $result);
        self::assertInstanceOf(\stdClass::class, $result->user);
        self::assertSame('Bob', $result->user->name);
        self::assertSame([1, 2, 3], $result->scores);
    }

    public function testRoundTripSimpleDto() : void
    {
        $obj = new SimpleDto('Alice', 30);

        $result = $this->packAndUnpack($obj);

        self::assertInstanceOf(SimpleDto::class, $result);
        self::assertSame('Alice', $result->name);
        self::assertSame(30, $result->age);
    }

    public function testRoundTripWithSerialize() : void
    {
        $obj = new SerializableDto('Alice', 30);

        $result = $this->packAndUnpack($obj);

        self::assertInstanceOf(SerializableDto::class, $result);
        self::assertSame('Alice', $result->name);
        self::assertSame(30, $result->age);
        // __unserialize rebuilds the computed field
        self::assertSame('Alice (30)', $result->computed);
    }

    public function testPublicPropsOnlyWithoutSerialize() : void
    {
        $obj = new PrivatePropsDto();

        $result = $this->packAndUnpack($obj);

        self::assertInstanceOf(PrivatePropsDto::class, $result);
        self::assertSame('public', $result->pub);
        // Protected/private are not captured by get_object_vars(),
        // so they keep their default values after reconstruction
        self::assertSame('protected', $result->getProt());
        self::assertSame('private', $result->getPriv());
    }

    public function testRoundTripNestedDifferentClasses() : void
    {
        $inner = new SimpleDto('inner', 1);
        $outer = (object) ['child' => $inner, 'label' => 'outer'];

        $result = $this->packAndUnpack($outer);

        self::assertInstanceOf(\stdClass::class, $result);
        self::assertSame('outer', $result->label);
        self::assertInstanceOf(SimpleDto::class, $result->child);
        self::assertSame('inner', $result->child->name);
        self::assertSame(1, $result->child->age);
    }

    public function testAllowedClassesAllowAll() : void
    {
        $ext = ObjectExtension::allowAll(self::EXT_TYPE);
        $packer = (new Packer())->extendWith($ext);
        $unpacker = (new BufferUnpacker())->extendWith($ext);

        $obj = new SimpleDto('test', 1);
        $packed = $packer->pack($obj);
        $result = $unpacker->reset($packed)->unpack();

        self::assertInstanceOf(SimpleDto::class, $result);
    }

    public function testDefaultDisallowsCustomClasses() : void
    {
        $packed = $this->packer->pack(new SimpleDto('test', 1));

        // Default ($allowedClasses = []) allows stdClass only
        $unpacker = (new BufferUnpacker())->extendWith(new ObjectExtension(self::EXT_TYPE));

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('is not in the allowed classes list');
        $unpacker->reset($packed)->unpack();
    }

    public function testAllowedClassesRestrictedThrows() : void
    {
        $packed = $this->packer->pack(new SimpleDto('test', 1));

        // Unpack with SimpleDto NOT in allowed list → throws
        $restrictedExt = new ObjectExtension(self::EXT_TYPE, [SerializableDto::class]);
        $unpacker = (new BufferUnpacker())->extendWith($restrictedExt);

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('is not in the allowed classes list');
        $unpacker->reset($packed)->unpack();
    }

    public function testAllowedClassesEmptyArrayThrows() : void
    {
        $packed = $this->packer->pack(new SimpleDto('test', 1));

        // [] = no classes allowed → throws
        $restrictedExt = new ObjectExtension(self::EXT_TYPE, []);
        $unpacker = (new BufferUnpacker())->extendWith($restrictedExt);

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('is not in the allowed classes list');
        $unpacker->reset($packed)->unpack();
    }

    public function testAllowedClassesPermitsListed() : void
    {
        $ext = new ObjectExtension(self::EXT_TYPE, [SimpleDto::class]);
        $packer = (new Packer())->extendWith($ext);
        $unpacker = (new BufferUnpacker())->extendWith($ext);

        $obj = new SimpleDto('test', 42);
        $packed = $packer->pack($obj);
        $result = $unpacker->reset($packed)->unpack();

        self::assertInstanceOf(SimpleDto::class, $result);
        self::assertSame('test', $result->name);
        self::assertSame(42, $result->age);
    }

    public function testStdClassAlwaysAllowed() : void
    {
        // Even with [] allowed, stdClass objects round-trip as stdClass
        $ext = new ObjectExtension(self::EXT_TYPE, []);
        $packer = (new Packer())->extendWith($ext);
        $unpacker = (new BufferUnpacker())->extendWith($ext);

        $obj = (object) ['x' => 1];
        $packed = $packer->pack($obj);
        $result = $unpacker->reset($packed)->unpack();

        self::assertInstanceOf(\stdClass::class, $result);
        self::assertSame(1, $result->x);
    }

    public function testUnknownClassWithNoRestrictionThrows() : void
    {
        $packed = $this->packer->pack(new SimpleDto('ghost', 99));

        // Mangle the class name by replacing it in the binary
        $search = SimpleDto::class;
        $replace = str_pad('No\\Such\\Class', strlen($search), '_');
        $packed = str_replace($search, $replace, $packed);

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('does not exist');
        $this->unpacker->reset($packed)->unpack();
    }

    public function testRoundTripReadonlyAndUninitializedProperties() : void
    {
        $obj = new ReadonlyAndUninitializedDto(42);

        $result = $this->packAndUnpack($obj);

        self::assertInstanceOf(ReadonlyAndUninitializedDto::class, $result);
        self::assertSame(42, $result->id);
        self::assertFalse(isset($result->uninitialized));
    }

    public function testRepeatedRoundTripUsesCachedMetadata() : void
    {
        $items = [
            new SimpleDto('Alice', 30),
            new SimpleDto('Bob', 25),
            UnbackedEnum::Foo,
            UnbackedEnum::Baz,
        ];

        $result = $this->unpacker->reset($this->packer->pack($items))->unpack();

        self::assertCount(4, $result);
        self::assertEquals($items[0], $result[0]);
        self::assertEquals($items[1], $result[1]);
        self::assertSame(UnbackedEnum::Foo, $result[2]);
        self::assertSame(UnbackedEnum::Baz, $result[3]);
    }

    public function testSkipsNonObject() : void
    {
        $ext = new ObjectExtension(self::EXT_TYPE);
        $packer = new Packer();

        self::assertNull($ext->pack($packer, 'foobar'));
        self::assertNull($ext->pack($packer, 42));
        self::assertNull($ext->pack($packer, ['a' => 1]));
        self::assertNull($ext->pack($packer, null));
    }

    /**
     * @dataProvider provideUnsupportedObjects
     */
    public function testUnsupportedObjectsThrowOnPack(object $obj) : void
    {
        $this->expectException(PackingFailedException::class);
        $this->packer->pack($obj);
    }

    public static function provideUnsupportedObjects() : iterable
    {
        yield 'closure' => [static function () : void {}];
        yield 'generator' => [(static function () : \Generator { yield 1; })()];
        yield 'anonymous class' => [new class {
            public int $x = 1;
        }];
    }

    public function testRoundTripUnbackedEnum() : void
    {
        $result = $this->packAndUnpack(UnbackedEnum::Foo);

        self::assertSame(UnbackedEnum::Foo, $result);
    }

    public function testRoundTripBackedEnum() : void
    {
        self::assertSame(StringBackedEnum::Foo, $this->packAndUnpack(StringBackedEnum::Foo));
    }

    public function testRoundTripEnumNestedInObject() : void
    {
        $obj = (object) [
            'unbacked' => UnbackedEnum::Bar,
            'backed' => StringBackedEnum::Bar,
        ];

        $result = $this->packAndUnpack($obj);

        self::assertInstanceOf(\stdClass::class, $result);
        self::assertSame(UnbackedEnum::Bar, $result->unbacked);
        self::assertSame(StringBackedEnum::Bar, $result->backed);
    }

    public function testAllowedClassesRestrictsEnum() : void
    {
        $packed = $this->packer->pack(UnbackedEnum::Foo);

        $restrictedExt = new ObjectExtension(self::EXT_TYPE, [SimpleDto::class]);
        $unpacker = (new BufferUnpacker())->extendWith($restrictedExt);

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('is not in the allowed classes list');
        $unpacker->reset($packed)->unpack();
    }

    public function testAllowedClassesPermitsEnum() : void
    {
        $ext = new ObjectExtension(self::EXT_TYPE, [UnbackedEnum::class]);
        $packer = (new Packer())->extendWith($ext);
        $unpacker = (new BufferUnpacker())->extendWith($ext);

        $packed = $packer->pack(UnbackedEnum::Baz);

        self::assertSame(UnbackedEnum::Baz, $unpacker->reset($packed)->unpack());
    }

    public function testUnknownEnumCaseThrows() : void
    {
        $packed = $this->packer->pack(UnbackedEnum::Bar);
        $packed = str_replace('Bar', 'Qux', $packed);

        $this->expectException(UnpackingFailedException::class);
        $this->expectExceptionMessage('does not exist');
        $this->unpacker->reset($packed)->unpack();
    }

    public function testGetType() : void
    {
        self::assertSame(0, (new ObjectExtension(0))->getType());
        self::assertSame(2, (new ObjectExtension(2))->getType());
        self::assertSame(127, (new ObjectExtension(127))->getType());
    }

    /**
     * @dataProvider provideInvalidTypes
     */
    public function testInvalidTypeThrows(int $type) : void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ObjectExtension($type);
    }

    public static function provideInvalidTypes() : iterable
    {
        yield [-1];
        yield [128];
    }

    private function packAndUnpack(object $obj) : mixed
    {
        $packed = $this->packer->pack($obj);

        return $this->unpacker->reset($packed)->unpack();
    }
}
