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
class Symfony_DI_PhpDumper_Test_Almost_Circular_Private extends Container
{
    private $parameters = [];

    public function __construct()
    {
        $this->services = $this->privates = [];
        $this->methodMap = [
            'bar2' => 'getBar2Service',
            'bar3' => 'getBar3Service',
            'baz6' => 'getBaz6Service',
            'connection' => 'getConnectionService',
            'connection2' => 'getConnection2Service',
            'doctrine.entity_manager' => 'getDoctrine_EntityManagerService',
            'foo' => 'getFooService',
            'foo2' => 'getFoo2Service',
            'foo5' => 'getFoo5Service',
            'foo6' => 'getFoo6Service',
            'foobar4' => 'getFoobar4Service',
            'listener3' => 'getListener3Service',
            'listener4' => 'getListener4Service',
            'logger' => 'getLoggerService',
            'manager' => 'getManagerService',
            'manager2' => 'getManager2Service',
            'manager3' => 'getManager3Service',
            'monolog.logger' => 'getMonolog_LoggerService',
            'monolog_inline.logger' => 'getMonologInline_LoggerService',
            'pA' => 'getPAService',
            'root' => 'getRootService',
            'subscriber' => 'getSubscriberService',
        ];

        $this->aliases = [];
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
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'bar' => true,
            'bar5' => true,
            'bar6' => true,
            'config' => true,
            'config2' => true,
            'connection3' => true,
            'connection4' => true,
            'dispatcher' => true,
            'dispatcher2' => true,
            'doctrine.config' => true,
            'doctrine.entity_listener_resolver' => true,
            'doctrine.listener' => true,
            'foo4' => true,
            'foobar' => true,
            'foobar2' => true,
            'foobar3' => true,
            'level2' => true,
            'level3' => true,
            'level4' => true,
            'level5' => true,
            'level6' => true,
            'logger2' => true,
            'mailer.transport' => true,
            'mailer.transport_factory' => true,
            'mailer.transport_factory.amazon' => true,
            'mailer_inline.mailer' => true,
            'mailer_inline.transport_factory' => true,
            'mailer_inline.transport_factory.amazon' => true,
            'manager4' => true,
            'monolog.logger_2' => true,
            'monolog_inline.logger_2' => true,
            'multiuse1' => true,
            'pB' => true,
            'pC' => true,
            'pD' => true,
            'subscriber2' => true,
        ];
    }

    /**
     * Gets the public 'bar2' shared service.
     *
     * @return \BarCircular
     */
    protected function getBar2Service()
    {
        $this->services['bar2'] = $instance = new \BarCircular();

        $instance->addFoobar(new \FoobarCircular(($this->services['foo2'] ?? $this->getFoo2Service())));

        return $instance;
    }

    /**
     * Gets the public 'bar3' shared service.
     *
     * @return \BarCircular
     */
    protected function getBar3Service()
    {
        $this->services['bar3'] = $instance = new \BarCircular();

        $a = new \FoobarCircular();

        $instance->addFoobar($a, $a);

        return $instance;
    }

    /**
     * Gets the public 'baz6' shared service.
     *
     * @return \stdClass
     */
    protected function getBaz6Service()
    {
        $this->services['baz6'] = $instance = new \stdClass();

        $instance->bar6 = ($this->privates['bar6'] ?? $this->getBar6Service());

        return $instance;
    }

    /**
     * Gets the public 'connection' shared service.
     *
     * @return \stdClass
     */
    protected function getConnectionService()
    {
        $a = new \stdClass();

        $b = new \stdClass();

        $this->services['connection'] = $instance = new \stdClass($a, $b);

        $b->logger = ($this->services['logger'] ?? $this->getLoggerService());

        $a->subscriber = ($this->services['subscriber'] ?? $this->getSubscriberService());

        return $instance;
    }

    /**
     * Gets the public 'connection2' shared service.
     *
     * @return \stdClass
     */
    protected function getConnection2Service()
    {
        $a = new \stdClass();

        $b = new \stdClass();

        $this->services['connection2'] = $instance = new \stdClass($a, $b);

        $c = new \stdClass($instance);

        $d = ($this->services['manager2'] ?? $this->getManager2Service());

        $c->handler2 = new \stdClass($d);

        $b->logger2 = $c;

        $a->subscriber2 = new \stdClass($d);

        return $instance;
    }

    /**
     * Gets the public 'doctrine.entity_manager' shared service.
     *
     * @return \stdClass
     */
    protected function getDoctrine_EntityManagerService()
    {
        $a = new \stdClass();
        $a->resolver = new \stdClass(new RewindableGenerator(function () {
            yield 0 => ($this->privates['doctrine.listener'] ?? $this->getDoctrine_ListenerService());
        }, 1));
        $a->flag = 'ok';

        return $this->services['doctrine.entity_manager'] = \FactoryChecker::create($a);
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \FooCircular
     */
    protected function getFooService()
    {
        $a = new \BarCircular();

        $this->services['foo'] = $instance = new \FooCircular($a);

        $a->addFoobar(new \FoobarCircular($instance));

        return $instance;
    }

    /**
     * Gets the public 'foo2' shared service.
     *
     * @return \FooCircular
     */
    protected function getFoo2Service()
    {
        $a = ($this->services['bar2'] ?? $this->getBar2Service());

        if (isset($this->services['foo2'])) {
            return $this->services['foo2'];
        }

        return $this->services['foo2'] = new \FooCircular($a);
    }

    /**
     * Gets the public 'foo5' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo5Service()
    {
        $this->services['foo5'] = $instance = new \stdClass();

        $a = new \stdClass($instance);
        $a->foo = $instance;

        $instance->bar = $a;

        return $instance;
    }

    /**
     * Gets the public 'foo6' shared service.
     *
     * @return \stdClass
     */
    protected function getFoo6Service()
    {
        $this->services['foo6'] = $instance = new \stdClass();

        $instance->bar6 = ($this->privates['bar6'] ?? $this->getBar6Service());

        return $instance;
    }

    /**
     * Gets the public 'foobar4' shared service.
     *
     * @return \stdClass
     */
    protected function getFoobar4Service()
    {
        $a = new \stdClass();

        $this->services['foobar4'] = $instance = new \stdClass($a);

        $a->foobar = $instance;

        return $instance;
    }

    /**
     * Gets the public 'listener3' shared service.
     *
     * @return \stdClass
     */
    protected function getListener3Service()
    {
        $this->services['listener3'] = $instance = new \stdClass();

        $instance->manager = ($this->services['manager3'] ?? $this->getManager3Service());

        return $instance;
    }

    /**
     * Gets the public 'listener4' shared service.
     *
     * @return \stdClass
     */
    protected function getListener4Service()
    {
        $a = ($this->privates['manager4'] ?? $this->getManager4Service());

        if (isset($this->services['listener4'])) {
            return $this->services['listener4'];
        }

        return $this->services['listener4'] = new \stdClass($a);
    }

    /**
     * Gets the public 'logger' shared service.
     *
     * @return \stdClass
     */
    protected function getLoggerService()
    {
        $a = ($this->services['connection'] ?? $this->getConnectionService());

        if (isset($this->services['logger'])) {
            return $this->services['logger'];
        }

        $this->services['logger'] = $instance = new \stdClass($a);

        $instance->handler = new \stdClass(($this->services['manager'] ?? $this->getManagerService()));

        return $instance;
    }

    /**
     * Gets the public 'manager' shared service.
     *
     * @return \stdClass
     */
    protected function getManagerService()
    {
        $a = ($this->services['connection'] ?? $this->getConnectionService());

        if (isset($this->services['manager'])) {
            return $this->services['manager'];
        }

        return $this->services['manager'] = new \stdClass($a);
    }

    /**
     * Gets the public 'manager2' shared service.
     *
     * @return \stdClass
     */
    protected function getManager2Service()
    {
        $a = ($this->services['connection2'] ?? $this->getConnection2Service());

        if (isset($this->services['manager2'])) {
            return $this->services['manager2'];
        }

        return $this->services['manager2'] = new \stdClass($a);
    }

    /**
     * Gets the public 'manager3' shared service.
     *
     * @return \stdClass
     */
    protected function getManager3Service($lazyLoad = true)
    {
        $a = ($this->services['listener3'] ?? $this->getListener3Service());

        if (isset($this->services['manager3'])) {
            return $this->services['manager3'];
        }
        $b = new \stdClass();
        $b->listener = [0 => $a];

        return $this->services['manager3'] = new \stdClass($b);
    }

    /**
     * Gets the public 'monolog.logger' shared service.
     *
     * @return \stdClass
     */
    protected function getMonolog_LoggerService()
    {
        $this->services['monolog.logger'] = $instance = new \stdClass();

        $instance->handler = ($this->privates['mailer.transport'] ?? $this->getMailer_TransportService());

        return $instance;
    }

    /**
     * Gets the public 'monolog_inline.logger' shared service.
     *
     * @return \stdClass
     */
    protected function getMonologInline_LoggerService()
    {
        $this->services['monolog_inline.logger'] = $instance = new \stdClass();

        $instance->handler = ($this->privates['mailer_inline.mailer'] ?? $this->getMailerInline_MailerService());

        return $instance;
    }

    /**
     * Gets the public 'pA' shared service.
     *
     * @return \stdClass
     */
    protected function getPAService()
    {
        $a = new \stdClass();

        $b = ($this->privates['pC'] ?? $this->getPCService());

        if (isset($this->services['pA'])) {
            return $this->services['pA'];
        }

        $this->services['pA'] = $instance = new \stdClass($a, $b);

        $a->d = ($this->privates['pD'] ?? $this->getPDService());

        return $instance;
    }

    /**
     * Gets the public 'root' shared service.
     *
     * @return \stdClass
     */
    protected function getRootService()
    {
        $a = new \Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls();

        $b = new \stdClass();

        $a->call(new \stdClass(new \stdClass($b, ($this->privates['level5'] ?? $this->getLevel5Service()))));

        return $this->services['root'] = new \stdClass($a, $b);
    }

    /**
     * Gets the public 'subscriber' shared service.
     *
     * @return \stdClass
     */
    protected function getSubscriberService()
    {
        $a = ($this->services['manager'] ?? $this->getManagerService());

        if (isset($this->services['subscriber'])) {
            return $this->services['subscriber'];
        }

        return $this->services['subscriber'] = new \stdClass($a);
    }

    /**
     * Gets the private 'bar6' shared service.
     *
     * @return \stdClass
     */
    protected function getBar6Service()
    {
        $a = ($this->services['foo6'] ?? $this->getFoo6Service());

        if (isset($this->privates['bar6'])) {
            return $this->privates['bar6'];
        }

        return $this->privates['bar6'] = new \stdClass($a);
    }

    /**
     * Gets the private 'doctrine.listener' shared service.
     *
     * @return \stdClass
     */
    protected function getDoctrine_ListenerService()
    {
        return $this->privates['doctrine.listener'] = new \stdClass(($this->services['doctrine.entity_manager'] ?? $this->getDoctrine_EntityManagerService()));
    }

    /**
     * Gets the private 'level5' shared service.
     *
     * @return \stdClass
     */
    protected function getLevel5Service()
    {
        $a = new \Symfony\Component\DependencyInjection\Tests\Fixtures\FooForCircularWithAddCalls();

        $this->privates['level5'] = $instance = new \stdClass($a);

        $a->call($instance);

        return $instance;
    }

    /**
     * Gets the private 'mailer.transport' shared service.
     *
     * @return \stdClass
     */
    protected function getMailer_TransportService()
    {
        return $this->privates['mailer.transport'] = (new \FactoryCircular(new RewindableGenerator(function () {
            yield 0 => ($this->privates['mailer.transport_factory.amazon'] ?? $this->getMailer_TransportFactory_AmazonService());
            yield 1 => $this->getMailerInline_TransportFactory_AmazonService();
        }, 2)))->create();
    }

    /**
     * Gets the private 'mailer.transport_factory.amazon' shared service.
     *
     * @return \stdClass
     */
    protected function getMailer_TransportFactory_AmazonService()
    {
        $a = new \stdClass();

        $this->privates['mailer.transport_factory.amazon'] = $instance = new \stdClass($a);

        $a->handler = ($this->privates['mailer.transport'] ?? $this->getMailer_TransportService());

        return $instance;
    }

    /**
     * Gets the private 'mailer_inline.mailer' shared service.
     *
     * @return \stdClass
     */
    protected function getMailerInline_MailerService()
    {
        return $this->privates['mailer_inline.mailer'] = new \stdClass((new \FactoryCircular(new RewindableGenerator(function () {
            return new \EmptyIterator();
        }, 0)))->create());
    }

    /**
     * Gets the private 'mailer_inline.transport_factory.amazon' shared service.
     *
     * @return \stdClass
     */
    protected function getMailerInline_TransportFactory_AmazonService()
    {
        $a = new \stdClass();
        $a->handler = ($this->privates['mailer_inline.mailer'] ?? $this->getMailerInline_MailerService());

        return new \stdClass($a);
    }

    /**
     * Gets the private 'manager4' shared service.
     *
     * @return \stdClass
     */
    protected function getManager4Service($lazyLoad = true)
    {
        $a = new \stdClass();

        $this->privates['manager4'] = $instance = new \stdClass($a);

        $a->listener = [0 => ($this->services['listener4'] ?? $this->getListener4Service())];

        return $instance;
    }

    /**
     * Gets the private 'pC' shared service.
     *
     * @return \stdClass
     */
    protected function getPCService($lazyLoad = true)
    {
        $this->privates['pC'] = $instance = new \stdClass();

        $instance->d = ($this->privates['pD'] ?? $this->getPDService());

        return $instance;
    }

    /**
     * Gets the private 'pD' shared service.
     *
     * @return \stdClass
     */
    protected function getPDService()
    {
        $a = ($this->services['pA'] ?? $this->getPAService());

        if (isset($this->privates['pD'])) {
            return $this->privates['pD'];
        }

        return $this->privates['pD'] = new \stdClass($a);
    }
}
