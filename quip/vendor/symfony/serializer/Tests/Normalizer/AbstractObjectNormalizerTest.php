<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\DummySecondChildQuux;

class AbstractObjectNormalizerTest extends TestCase
{
    public function testDenormalize()
    {
        $normalizer = new AbstractObjectNormalizerDummy();
        $normalizedData = $normalizer->denormalize(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'], Dummy::class);

        $this->assertSame('foo', $normalizedData->foo);
        $this->assertNull($normalizedData->bar);
        $this->assertSame('baz', $normalizedData->baz);
    }

    public function testInstantiateObjectDenormalizer()
    {
        $data = ['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'];
        $class = Dummy::class;
        $context = [];

        $normalizer = new AbstractObjectNormalizerDummy();

        $this->assertInstanceOf(Dummy::class, $normalizer->instantiateObject($data, $class, $context, new \ReflectionClass($class), []));
    }

    public function testDenormalizeWithExtraAttributes()
    {
        $this->expectException(ExtraAttributesException::class);
        $this->expectExceptionMessage('Extra attributes are not allowed ("fooFoo", "fooBar" are unknown).');
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new AbstractObjectNormalizerDummy($factory);
        $normalizer->denormalize(
            ['fooFoo' => 'foo', 'fooBar' => 'bar'],
            Dummy::class,
            'any',
            ['allow_extra_attributes' => false]
        );
    }

    public function testDenormalizeWithExtraAttributesAndNoGroupsWithMetadataFactory()
    {
        $this->expectException(ExtraAttributesException::class);
        $this->expectExceptionMessage('Extra attributes are not allowed ("fooFoo", "fooBar" are unknown).');
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $normalizer->denormalize(
            ['fooFoo' => 'foo', 'fooBar' => 'bar', 'bar' => 'bar'],
            Dummy::class,
            'any',
            ['allow_extra_attributes' => false]
        );
    }

    public function testDenormalizeCollectionDecodedFromXmlWithOneChild()
    {
        $denormalizer = $this->getDenormalizerForDummyCollection();

        $dummyCollection = $denormalizer->denormalize(
            [
                'children' => [
                    'bar' => 'first',
                ],
            ],
            DummyCollection::class,
            'xml'
        );

        $this->assertInstanceOf(DummyCollection::class, $dummyCollection);
        $this->assertIsArray($dummyCollection->children);
        $this->assertCount(1, $dummyCollection->children);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[0]);
    }

    public function testDenormalizeCollectionDecodedFromXmlWithTwoChildren()
    {
        $denormalizer = $this->getDenormalizerForDummyCollection();

        $dummyCollection = $denormalizer->denormalize(
            [
                'children' => [
                    ['bar' => 'first'],
                    ['bar' => 'second'],
                ],
            ],
            DummyCollection::class,
            'xml'
        );

        $this->assertInstanceOf(DummyCollection::class, $dummyCollection);
        $this->assertIsArray($dummyCollection->children);
        $this->assertCount(2, $dummyCollection->children);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[0]);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[1]);
    }

    private function getDenormalizerForDummyCollection()
    {
        $extractor = $this->createMock(PhpDocExtractor::class);
        $extractor->method('getTypes')
            ->will($this->onConsecutiveCalls(
                [new Type('array', false, null, true, new Type('int'), new Type('object', false, DummyChild::class))],
                null
            ));

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy([$arrayDenormalizer, $denormalizer]);
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    public function testDenormalizeStringCollectionDecodedFromXmlWithOneChild()
    {
        $denormalizer = $this->getDenormalizerForStringCollection();

        // if an xml-node can have children which should be deserialized as string[]
        // and only one child exists
        $stringCollection = $denormalizer->denormalize(['children' => 'foo'], StringCollection::class, 'xml');

        $this->assertInstanceOf(StringCollection::class, $stringCollection);
        $this->assertIsArray($stringCollection->children);
        $this->assertCount(1, $stringCollection->children);
        $this->assertEquals('foo', $stringCollection->children[0]);
    }

    public function testDenormalizeStringCollectionDecodedFromXmlWithTwoChildren()
    {
        $denormalizer = $this->getDenormalizerForStringCollection();

        // if an xml-node can have children which should be deserialized as string[]
        // and only one child exists
        $stringCollection = $denormalizer->denormalize(['children' => ['foo', 'bar']], StringCollection::class, 'xml');

        $this->assertInstanceOf(StringCollection::class, $stringCollection);
        $this->assertIsArray($stringCollection->children);
        $this->assertCount(2, $stringCollection->children);
        $this->assertEquals('foo', $stringCollection->children[0]);
        $this->assertEquals('bar', $stringCollection->children[1]);
    }

    public function testDenormalizeNotSerializableObjectToPopulate()
    {
        $normalizer = new AbstractObjectNormalizerDummy();
        $normalizedData = $normalizer->denormalize(['foo' => 'foo'], Dummy::class, null, [AbstractObjectNormalizer::OBJECT_TO_POPULATE => new NotSerializable()]);

        $this->assertSame('foo', $normalizedData->foo);
    }

    private function getDenormalizerForStringCollection()
    {
        $extractor = $this->createMock(PhpDocExtractor::class);
        $extractor->method('getTypes')
            ->will($this->onConsecutiveCalls(
                [new Type('array', false, null, true, new Type('int'), new Type('string'))],
                null
            ));

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy([$arrayDenormalizer, $denormalizer]);
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    public function testDenormalizeWithDiscriminatorMapUsesCorrectClassname()
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $loaderMock = new class() implements ClassMetadataFactoryInterface {
            public function getMetadataFor($value): ClassMetadataInterface
            {
                if (AbstractDummy::class === $value) {
                    return new ClassMetadata(
                        AbstractDummy::class,
                        new ClassDiscriminatorMapping('type', [
                            'first' => AbstractDummyFirstChild::class,
                            'second' => AbstractDummySecondChild::class,
                        ])
                    );
                }

                throw new InvalidArgumentException();
            }

            public function hasMetadataFor($value): bool
            {
                return AbstractDummy::class === $value;
            }
        };

        $discriminatorResolver = new ClassDiscriminatorFromClassMetadata($loaderMock);
        $normalizer = new AbstractObjectNormalizerDummy($factory, null, new PhpDocExtractor(), $discriminatorResolver);
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);
        $normalizedData = $normalizer->denormalize(['foo' => 'foo', 'baz' => 'baz', 'quux' => ['value' => 'quux'], 'type' => 'second'], AbstractDummy::class);

        $this->assertInstanceOf(DummySecondChildQuux::class, $normalizedData->quux);
    }

    public function testDenormalizeWithNestedDiscriminatorMap()
    {
        $classDiscriminatorResolver = new class() implements ClassDiscriminatorResolverInterface {
            public function getMappingForClass(string $class): ?ClassDiscriminatorMapping
            {
                switch ($class) {
                    case AbstractDummy::class:
                        return new ClassDiscriminatorMapping('type', [
                            'foo' => AbstractDummyFirstChild::class,
                        ]);
                    case AbstractDummyFirstChild::class:
                        return new ClassDiscriminatorMapping('nested_type', [
                            'bar' => AbstractDummySecondChild::class,
                        ]);
                    default:
                        return null;
                }
            }

            public function getMappingForMappedObject($object): ?ClassDiscriminatorMapping
            {
                return null;
            }

            public function getTypeForMappedObject($object): ?string
            {
                return null;
            }
        };

        $normalizer = new AbstractObjectNormalizerDummy(null, null, null, $classDiscriminatorResolver);

        $denormalizedData = $normalizer->denormalize(['type' => 'foo', 'nested_type' => 'bar'], AbstractDummy::class);

        $this->assertInstanceOf(AbstractDummySecondChild::class, $denormalizedData);
    }

    /**
     * Test that additional attributes throw an exception if no metadata factory is specified.
     */
    public function testExtraAttributesException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A class metadata factory must be provided in the constructor when setting "allow_extra_attributes" to false.');
        $normalizer = new ObjectNormalizer();

        $normalizer->denormalize([], \stdClass::class, 'xml', [
            'allow_extra_attributes' => false,
        ]);
    }

    public function testNormalizeEmptyObject()
    {
        $normalizer = new AbstractObjectNormalizerDummy();

        // This results in objects turning into arrays in some encoders
        $normalizedData = $normalizer->normalize(new EmptyDummy());
        $this->assertEquals([], $normalizedData);

        $normalizedData = $normalizer->normalize(new EmptyDummy(), 'any', ['preserve_empty_objects' => true]);
        $this->assertEquals(new \ArrayObject(), $normalizedData);
    }
}

class AbstractObjectNormalizerDummy extends AbstractObjectNormalizer
{
    protected function extractAttributes($object, $format = null, array $context = []): array
    {
        return [];
    }

    protected function getAttributeValue($object, $attribute, $format = null, array $context = [])
    {
    }

    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = [])
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = []): bool
    {
        return \in_array($attribute, ['foo', 'baz', 'quux', 'value']);
    }

    /**
     * @return object
     */
    public function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null)
    {
        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }
}

class Dummy
{
    public $foo;
    public $bar;
    public $baz;
}

class EmptyDummy
{
}

class AbstractObjectNormalizerWithMetadata extends AbstractObjectNormalizer
{
    public function __construct()
    {
        parent::__construct(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
    }

    protected function extractAttributes($object, $format = null, array $context = []): array
    {
    }

    protected function getAttributeValue($object, $attribute, $format = null, array $context = [])
    {
    }

    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = [])
    {
        $object->$attribute = $value;
    }
}

class StringCollection
{
    /** @var string[] */
    public $children;
}

class DummyCollection
{
    /** @var DummyChild[] */
    public $children;
}

class DummyChild
{
    public $bar;
}

class SerializerCollectionDummy implements SerializerInterface, DenormalizerInterface
{
    private $normalizers;

    /**
     * @param DenormalizerInterface[] $normalizers
     */
    public function __construct($normalizers)
    {
        $this->normalizers = $normalizers;
    }

    public function serialize($data, $format, array $context = []): string
    {
    }

    public function deserialize($data, $type, $format, array $context = [])
    {
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof DenormalizerInterface && $normalizer->supportsDenormalization($data, $type, $format, $context)) {
                return $normalizer->denormalize($data, $type, $format, $context);
            }
        }

        return null;
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return true;
    }
}

class AbstractObjectNormalizerCollectionDummy extends AbstractObjectNormalizer
{
    protected function extractAttributes($object, $format = null, array $context = []): array
    {
    }

    protected function getAttributeValue($object, $attribute, $format = null, array $context = [])
    {
    }

    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = [])
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = []): bool
    {
        return true;
    }

    /**
     * @return object
     */
    public function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null)
    {
        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }

    public function serialize($data, $format, array $context = [])
    {
    }

    public function deserialize($data, $type, $format, array $context = [])
    {
    }
}

class ArrayDenormalizerDummy implements DenormalizerInterface, SerializerAwareInterface
{
    /**
     * @var SerializerInterface|DenormalizerInterface
     */
    private $serializer;

    /**
     * {@inheritdoc}
     *
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $serializer = $this->serializer;
        $type = substr($type, 0, -2);

        foreach ($data as $key => $value) {
            $data[$key] = $serializer->denormalize($value, $type, $format, $context);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return str_ends_with($type, '[]')
            && $this->serializer->supportsDenormalization($data, substr($type, 0, -2), $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }
}

class NotSerializable
{
    public function __sleep()
    {
        if (class_exists(\Error::class)) {
            throw new \Error('not serializable');
        }

        throw new \Exception('not serializable');
    }
}
