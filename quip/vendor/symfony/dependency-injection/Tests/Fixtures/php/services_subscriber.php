<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final
 */
class ProjectServiceContainer extends Container
{
    private $parameters = [];
    private $getService;

    public function __construct()
    {
        $this->getService = \Closure::fromCallable([$this, 'getService']);
        $this->services = $this->privates = [];
        $this->methodMap = [
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => 'getTestServiceSubscriberService',
            'foo_service' => 'getFooServiceService',
            'late_alias' => 'getLateAliasService',
        ];
        $this->aliases = [
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestDefinition1' => 'late_alias',
        ];
    }

    public function compile(): void
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled(): bool
    {
        return true;
    }

    public function getRemovedIds(): array
    {
        return [
            '.service_locator.CpwjbIa' => true,
            '.service_locator.CpwjbIa.foo_service' => true,
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => true,
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestDefinition1' => true,
            'late_alias' => true,
        ];
    }

    /**
     * Gets the public 'Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber
     */
    protected function getTestServiceSubscriberService()
    {
        return $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber();
    }

    /**
     * Gets the public 'foo_service' shared autowired service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber((new \Symfony\Component\DependencyInjection\Argument\ServiceLocator($this->getService, [
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => ['privates', 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition', 'getCustomDefinitionService', false],
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => ['services', 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber', 'getTestServiceSubscriberService', false],
            'bar' => ['services', 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber', 'getTestServiceSubscriberService', false],
            'baz' => ['privates', 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition', 'getCustomDefinitionService', false],
            'late_alias' => ['services', 'late_alias', 'getLateAliasService', false],
        ], [
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition',
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber',
            'bar' => 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition',
            'baz' => 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition',
            'late_alias' => 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestDefinition1',
        ]))->withContext('foo_service', $this));
    }

    /**
     * Gets the public 'late_alias' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition1
     */
    protected function getLateAliasService()
    {
        return $this->services['late_alias'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition1();
    }

    /**
     * Gets the private 'Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition
     */
    protected function getCustomDefinitionService()
    {
        return $this->privates['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition();
    }
}
