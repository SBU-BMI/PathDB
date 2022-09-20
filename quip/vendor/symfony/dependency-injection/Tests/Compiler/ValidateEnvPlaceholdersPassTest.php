<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Exception\TreeWithoutRootNodeException;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\RegisterEnvVarProcessorsPass;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class ValidateEnvPlaceholdersPassTest extends TestCase
{
    public function testEnvsAreValidatedInConfig()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(NULLED)', null);
        $container->setParameter('env(FLOATISH)', '3.2');
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = [
            'scalar_node' => '%env(NULLED)%',
            'scalar_node_not_empty' => '%env(FLOATISH)%',
            'int_node' => '%env(int:FOO)%',
            'float_node' => '%env(float:BAR)%',
            'string_node' => '%env(UNDEFINED)%',
        ]);

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testDefaultEnvIsValidatedInConfig()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "env_extension.string_node": "fail" is not a valid string');
        $container = new ContainerBuilder();
        $container->setParameter('env(STRING)', 'fail');
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = [
            'string_node' => '%env(STRING)%',
        ]);

        $this->doProcess($container);
    }

    /**
     * @group legacy
     * @expectedDeprecation A non-string default value of an env() parameter is deprecated since 4.3, cast "env(FLOATISH)" to string instead.
     */
    public function testDefaultEnvWithoutPrefixIsValidatedInConfig()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(FLOATISH)', 3.2);
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = [
            'float_node' => '%env(FLOATISH)%',
        ]);

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testEnvsAreValidatedInConfigWithInvalidPlaceholder()
    {
        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage('Invalid type for path "env_extension.bool_node". Expected "bool", but got one of "bool", "int", "float", "string", "array".');
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = [
            'bool_node' => '%env(const:BAZ)%',
        ]);

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testInvalidEnvInConfig()
    {
        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage('Invalid type for path "env_extension.int_node". Expected "int", but got "array".');
        $container = new ContainerBuilder();
        $container->registerExtension(new EnvExtension());
        $container->prependExtensionConfig('env_extension', [
            'int_node' => '%env(json:FOO)%',
        ]);

        $this->doProcess($container);
    }

    public function testNulledEnvInConfig()
    {
        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessageMatches('/^Invalid type for path "env_extension\.int_node"\. Expected "?int"?, but got (NULL|"null")\.$/');
        $container = new ContainerBuilder();
        $container->setParameter('env(NULLED)', null);
        $container->registerExtension(new EnvExtension());
        $container->prependExtensionConfig('env_extension', [
            'int_node' => '%env(NULLED)%',
        ]);

        $this->doProcess($container);
    }

    public function testValidateEnvOnMerge()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', [
            'int_node' => '%env(int:const:FOO)%',
            'bool_node' => true,
        ]);
        $container->prependExtensionConfig('env_extension', [
            'int_node' => '%env(int:BAR)%',
            'bool_node' => '%env(bool:int:BAZ)%',
            'scalar_node' => '%env(BAZ)%',
        ]);

        $this->doProcess($container);

        $expected = [
            'int_node' => '%env(int:const:FOO)%',
            'bool_node' => true,
            'scalar_node' => '%env(BAZ)%',
        ];

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testConcatenatedEnvInConfig()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', [
            'scalar_node' => $expected = 'foo %env(BAR)% baz',
        ]);

        $this->doProcess($container);

        $this->assertSame(['scalar_node' => $expected], $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testEnvIsIncompatibleWithArrayNode()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('A dynamic value is not compatible with a "Symfony\Component\Config\Definition\ArrayNode" node type at path "env_extension.simple_array_node".');
        $container = new ContainerBuilder();
        $container->registerExtension(new EnvExtension());
        $container->prependExtensionConfig('env_extension', [
            'simple_array_node' => '%env(json:FOO)%',
        ]);

        $this->doProcess($container);
    }

    public function testNormalizedEnvIsCompatibleWithArrayNode()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', [
            'array_node' => $expected = '%env(CHILD)%',
        ]);

        $this->doProcess($container);

        $this->assertSame(['array_node' => ['child_node' => $expected]], $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testEnvIsNotUnset()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = [
            'array_node' => ['int_unset_at_zero' => '%env(int:CHILD)%'],
        ]);

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testEmptyEnvWhichCannotBeEmptyForScalarNode()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = [
            'scalar_node_not_empty' => '%env(SOME)%',
        ]);

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    /**
     * NOT LEGACY (test exception in 5.0).
     *
     * @group legacy
     * @expectedDeprecation Setting path "env_extension.scalar_node_not_empty_validated" to an environment variable is deprecated since Symfony 4.3. Remove "cannotBeEmpty()", "validate()" or include a prefix/suffix value instead.
     */
    public function testEmptyEnvWhichCannotBeEmptyForScalarNodeWithValidation()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = [
            'scalar_node_not_empty_validated' => '%env(SOME)%',
        ]);

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testPartialEnvWhichCannotBeEmptyForScalarNode()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = [
            'scalar_node_not_empty_validated' => 'foo %env(SOME)% bar',
        ]);

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testEnvWithVariableNode()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = [
            'variable_node' => '%env(SOME)%',
        ]);

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    /**
     * @group legacy
     * @expectedDeprecation A tree builder without a root node is deprecated since Symfony 4.2 and will not be supported anymore in 5.0.
     */
    public function testConfigurationWithoutRootNode()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new EnvExtension(new EnvConfigurationWithoutRootNode()));
        $container->loadFromExtension('env_extension', ['foo' => 'bar']);

        (new ValidateEnvPlaceholdersPass())->process($container);
    }

    public function testEmptyConfigFromMoreThanOneSource()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new EnvExtension(new ConfigurationWithArrayNodeRequiringOneElement()));
        $container->loadFromExtension('env_extension', []);

        $this->doProcess($container);

        $this->addToAssertionCount(1);
    }

    public function testDiscardedEnvInConfig()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(BOOLISH)', '1');
        $container->setParameter('boolish', '%env(BOOLISH)%');
        $container->registerExtension(new EnvExtension());
        $container->prependExtensionConfig('env_extension', [
            'array_node' => ['bool_force_cast' => '%boolish%'],
        ]);

        $container->compile(true);

        $this->assertSame('1', $container->getParameter('boolish'));
    }

    private function doProcess(ContainerBuilder $container): void
    {
        (new MergeExtensionConfigurationPass())->process($container);
        (new RegisterEnvVarProcessorsPass())->process($container);
        (new ValidateEnvPlaceholdersPass())->process($container);
    }
}

class EnvConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('env_extension');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('scalar_node')->end()
                ->scalarNode('scalar_node_not_empty')->cannotBeEmpty()->end()
                ->scalarNode('scalar_node_not_empty_validated')
                    ->cannotBeEmpty()
                    ->validate()
                        ->always(function ($value) {
                            return $value;
                        })
                    ->end()
                ->end()
                ->integerNode('int_node')->end()
                ->floatNode('float_node')->end()
                ->booleanNode('bool_node')->end()
                ->arrayNode('array_node')
                    ->beforeNormalization()
                        ->ifTrue(function ($value) { return !\is_array($value); })
                        ->then(function ($value) { return ['child_node' => $value]; })
                    ->end()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(function (array $v) {
                            if (isset($v['bool_force_cast'])) {
                                $v['bool_force_cast'] = (bool) $v['bool_force_cast'];
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('child_node')->end()
                        ->booleanNode('bool_force_cast')->end()
                        ->integerNode('int_unset_at_zero')
                            ->validate()
                                ->ifTrue(function ($value) { return 0 === $value; })
                                ->thenUnset()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('simple_array_node')->end()
                ->enumNode('enum_node')->values(['a', 'b'])->end()
                ->variableNode('variable_node')->end()
                ->scalarNode('string_node')
                    ->validate()
                        ->ifTrue(function ($value) {
                            return !\is_string($value) || 'fail' === $value;
                        })
                        ->thenInvalid('%s is not a valid string')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

class EnvConfigurationWithoutRootNode implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder();
    }
}

class ConfigurationWithArrayNodeRequiringOneElement implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('env_extension');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('nodes')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

class EnvExtension extends Extension
{
    private $configuration;
    private $config;

    public function __construct(ConfigurationInterface $configuration = null)
    {
        $this->configuration = $configuration ?? new EnvConfiguration();
    }

    public function getAlias(): string
    {
        return 'env_extension';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return $this->configuration;
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        if (!array_filter($configs)) {
            return;
        }

        try {
            $this->config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        } catch (TreeWithoutRootNodeException $e) {
            $this->config = null;
        }
    }

    public function getConfig()
    {
        return $this->config;
    }
}
