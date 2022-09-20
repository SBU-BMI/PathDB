<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Mapping\Cache\CacheInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\Validator\Util\LegacyTranslatorProxy;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Component\Validator\ValidatorBuilderInterface;

class ValidatorBuilderTest extends TestCase
{
    /**
     * @var ValidatorBuilderInterface
     */
    protected $builder;

    protected function setUp(): void
    {
        $this->builder = new ValidatorBuilder();
    }

    protected function tearDown(): void
    {
        $this->builder = null;
    }

    public function testAddObjectInitializer()
    {
        $this->assertSame($this->builder, $this->builder->addObjectInitializer(
            $this->createMock(ObjectInitializerInterface::class)
        ));
    }

    public function testAddObjectInitializers()
    {
        $this->assertSame($this->builder, $this->builder->addObjectInitializers([]));
    }

    public function testAddXmlMapping()
    {
        $this->assertSame($this->builder, $this->builder->addXmlMapping('mapping'));
    }

    public function testAddXmlMappings()
    {
        $this->assertSame($this->builder, $this->builder->addXmlMappings([]));
    }

    public function testAddYamlMapping()
    {
        $this->assertSame($this->builder, $this->builder->addYamlMapping('mapping'));
    }

    public function testAddYamlMappings()
    {
        $this->assertSame($this->builder, $this->builder->addYamlMappings([]));
    }

    public function testAddMethodMapping()
    {
        $this->assertSame($this->builder, $this->builder->addMethodMapping('mapping'));
    }

    public function testAddMethodMappings()
    {
        $this->assertSame($this->builder, $this->builder->addMethodMappings([]));
    }

    public function testEnableAnnotationMapping()
    {
        $this->assertSame($this->builder, $this->builder->enableAnnotationMapping());
    }

    public function testDisableAnnotationMapping()
    {
        $this->assertSame($this->builder, $this->builder->disableAnnotationMapping());
    }

    public function testSetMappingCache()
    {
        $this->assertSame($this->builder, $this->builder->setMappingCache($this->createMock(CacheItemPoolInterface::class)));
    }

    /**
     * @group legacy
     * @expectedDeprecation Symfony\Component\Validator\ValidatorBuilder::setMetadataCache is deprecated since Symfony 4.4. Use setMappingCache() instead.
     */
    public function testSetMetadataCache()
    {
        $this->assertSame($this->builder, $this->builder->setMetadataCache(
            $this->createMock(CacheInterface::class))
        );
    }

    public function testSetConstraintValidatorFactory()
    {
        $this->assertSame($this->builder, $this->builder->setConstraintValidatorFactory(
            $this->createMock(ConstraintValidatorFactoryInterface::class))
        );
    }

    public function testSetTranslator()
    {
        $this->assertSame($this->builder, $this->builder->setTranslator(
            $this->createMock(TranslatorInterface::class))
        );
    }

    public function testLegacyTranslatorProxy()
    {
        $proxy = $this->createMock(LegacyTranslatorProxy::class);
        $proxy->expects($this->once())->method('getTranslator');

        $this->builder->setTranslator($proxy);
    }

    public function testSetTranslationDomain()
    {
        $this->assertSame($this->builder, $this->builder->setTranslationDomain('TRANS_DOMAIN'));
    }

    public function testGetValidator()
    {
        $this->assertInstanceOf(RecursiveValidator::class, $this->builder->getValidator());
    }
}
