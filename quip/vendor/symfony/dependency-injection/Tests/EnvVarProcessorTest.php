<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;
use Symfony\Component\DependencyInjection\EnvVarProcessor;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ParameterCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

class EnvVarProcessorTest extends TestCase
{
    public const TEST_CONST = 'test';

    /**
     * @dataProvider validStrings
     */
    public function testGetEnvString($value, $processed)
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(foo)', $value);
        $container->compile();

        $processor = new EnvVarProcessor($container);

        $result = $processor->getEnv('string', 'foo', function () {
            $this->fail('Should not be called');
        });

        $this->assertSame($processed, $result);
    }

    public function validStrings()
    {
        return [
            ['hello', 'hello'],
            ['true', 'true'],
            ['false', 'false'],
            ['null', 'null'],
            ['1', '1'],
            ['0', '0'],
            ['1.1', '1.1'],
            ['1e1', '1e1'],
        ];
    }

    /**
     * @dataProvider validBools
     */
    public function testGetEnvBool($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('bool', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validBools()
    {
        return [
            ['true', true],
            ['false', false],
            ['null', false],
            ['1', true],
            ['0', false],
            ['1.1', true],
            ['1e1', true],
        ];
    }

    /**
     * @dataProvider validInts
     */
    public function testGetEnvInt($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('int', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validInts()
    {
        return [
            ['1', 1],
            ['1.1', 1],
            ['1e1', 10],
        ];
    }

    /**
     * @dataProvider invalidInts
     */
    public function testGetEnvIntInvalid($value)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Non-numeric env var');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('int', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidInts()
    {
        return [
            ['foo'],
            ['true'],
            ['null'],
        ];
    }

    /**
     * @dataProvider validFloats
     */
    public function testGetEnvFloat($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('float', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validFloats()
    {
        return [
            ['1', 1.0],
            ['1.1', 1.1],
            ['1e1', 10.0],
        ];
    }

    /**
     * @dataProvider invalidFloats
     */
    public function testGetEnvFloatInvalid($value)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Non-numeric env var');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('float', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidFloats()
    {
        return [
            ['foo'],
            ['true'],
            ['null'],
        ];
    }

    /**
     * @dataProvider validConsts
     */
    public function testGetEnvConst($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('const', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validConsts()
    {
        return [
            ['Symfony\Component\DependencyInjection\Tests\EnvVarProcessorTest::TEST_CONST', self::TEST_CONST],
            ['E_ERROR', \E_ERROR],
        ];
    }

    /**
     * @dataProvider invalidConsts
     */
    public function testGetEnvConstInvalid($value)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('undefined constant');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('const', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidConsts()
    {
        return [
            ['Symfony\Component\DependencyInjection\Tests\EnvVarProcessorTest::UNDEFINED_CONST'],
            ['UNDEFINED_CONST'],
        ];
    }

    public function testGetEnvBase64()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('base64', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return base64_encode('hello');
        });

        $this->assertSame('hello', $result);

        $result = $processor->getEnv('base64', 'foo', function ($name) { return '/+0='; });
        $this->assertSame("\xFF\xED", $result);

        $result = $processor->getEnv('base64', 'foo', function ($name) { return '_-0='; });
        $this->assertSame("\xFF\xED", $result);
    }

    public function testGetEnvTrim()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('trim', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return " hello\n";
        });

        $this->assertSame('hello', $result);
    }

    /**
     * @dataProvider validJson
     */
    public function testGetEnvJson($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('json', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validJson()
    {
        return [
            ['[1]', [1]],
            ['{"key": "value"}', ['key' => 'value']],
            [null, null],
        ];
    }

    public function testGetEnvInvalidJson()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Syntax error');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('json', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return 'invalid_json';
        });
    }

    /**
     * @dataProvider otherJsonValues
     */
    public function testGetEnvJsonOther($value)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON env var');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('json', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return json_encode($value);
        });
    }

    public function otherJsonValues()
    {
        return [
            [1],
            [1.1],
            [true],
            [false],
            ['foo'],
        ];
    }

    public function testGetEnvUnknown()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported env var prefix');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('unknown', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return 'foo';
        });
    }

    public function testGetEnvKeyInvalidKey()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid env "key:foo": a key specifier should be provided.');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('key', 'foo', function ($name) {
            $this->fail('Should not get here');
        });
    }

    /**
     * @dataProvider noArrayValues
     */
    public function testGetEnvKeyNoArrayResult($value)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Resolved value of "foo" did not result in an array value.');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('key', 'index:foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function noArrayValues()
    {
        return [
            [null],
            ['string'],
            [1],
            [true],
        ];
    }

    /**
     * @dataProvider invalidArrayValues
     */
    public function testGetEnvKeyArrayKeyNotFound($value)
    {
        $this->expectException(EnvNotFoundException::class);
        $this->expectExceptionMessage('Key "index" not found in');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('key', 'index:foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidArrayValues()
    {
        return [
            [[]],
            [['index2' => 'value']],
            [['index', 'index2']],
        ];
    }

    /**
     * @dataProvider arrayValues
     */
    public function testGetEnvKey($value)
    {
        $processor = new EnvVarProcessor(new Container());

        $this->assertSame($value['index'], $processor->getEnv('key', 'index:foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        }));
    }

    public function arrayValues()
    {
        return [
            [['index' => 'password']],
            [['index' => 'true']],
            [['index' => false]],
            [['index' => '1']],
            [['index' => 1]],
            [['index' => '1.1']],
            [['index' => 1.1]],
            [['index' => []]],
            [['index' => ['val1', 'val2']]],
        ];
    }

    public function testGetEnvKeyChained()
    {
        $processor = new EnvVarProcessor(new Container());

        $this->assertSame('password', $processor->getEnv('key', 'index:file:foo', function ($name) {
            $this->assertSame('file:foo', $name);

            return [
                'index' => 'password',
            ];
        }));
    }

    /**
     * @dataProvider validNullables
     */
    public function testGetEnvNullable($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());
        $result = $processor->getEnv('default', ':foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });
        $this->assertSame($processed, $result);
    }

    public function validNullables()
    {
        return [
            ['hello', 'hello'],
            ['', null],
            ['null', 'null'],
            ['Null', 'Null'],
            ['NULL', 'NULL'],
         ];
    }

    public function testRequireMissingFile()
    {
        $this->expectException(EnvNotFoundException::class);
        $this->expectExceptionMessage('missing-file');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('require', '/missing-file', function ($name) {
            return $name;
        });
    }

    public function testRequireFile()
    {
        $path = __DIR__.'/Fixtures/php/return_foo_string.php';

        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('require', $path, function ($name) use ($path) {
            $this->assertSame($path, $name);

            return $path;
        });

        $this->assertEquals('foo', $result);
    }

    /**
     * @dataProvider validResolve
     */
    public function testGetEnvResolve($value, $processed)
    {
        $container = new ContainerBuilder();
        $container->setParameter('bar', $value);
        $container->compile();

        $processor = new EnvVarProcessor($container);

        $result = $processor->getEnv('resolve', 'foo', function () {
            return '%bar%';
        });

        $this->assertSame($processed, $result);
    }

    public function validResolve()
    {
        return [
            ['string', 'string'],
            [1, '1'],
            [1.1, '1.1'],
            [true, '1'],
            [false, ''],
        ];
    }

    public function testGetEnvResolveNoMatch()
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('resolve', 'foo', function () {
            return '%%';
        });

        $this->assertSame('%', $result);
    }

    /**
     * @dataProvider notScalarResolve
     */
    public function testGetEnvResolveNotScalar($value)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parameter "bar" found when resolving env var "foo" must be scalar');

        $container = new ContainerBuilder();
        $container->setParameter('bar', $value);
        $container->compile();

        $processor = new EnvVarProcessor($container);

        $processor->getEnv('resolve', 'foo', function () {
            return '%bar%';
        });
    }

    public function notScalarResolve()
    {
        return [
            [null],
            [[]],
        ];
    }

    public function testGetEnvResolveNestedEnv()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(BAR)', 'BAR in container');
        $container->compile();

        $processor = new EnvVarProcessor($container);
        $getEnv = \Closure::fromCallable([$processor, 'getEnv']);

        $result = $processor->getEnv('resolve', 'foo', function ($name) use ($getEnv) {
            return 'foo' === $name ? '%env(BAR)%' : $getEnv('string', $name, function () {});
        });

        $this->assertSame('BAR in container', $result);
    }

    public function testGetEnvResolveNestedRealEnv()
    {
        $_ENV['BAR'] = 'BAR in environment';

        $container = new ContainerBuilder();
        $container->setParameter('env(BAR)', 'BAR in container');
        $container->compile();

        $processor = new EnvVarProcessor($container);
        $getEnv = \Closure::fromCallable([$processor, 'getEnv']);

        $result = $processor->getEnv('resolve', 'foo', function ($name) use ($getEnv) {
            return 'foo' === $name ? '%env(BAR)%' : $getEnv('string', $name, function () {});
        });

        $this->assertSame('BAR in environment', $result);

        unset($_ENV['BAR']);
    }

    /**
     * @dataProvider validCsv
     */
    public function testGetEnvCsv($value, $processed)
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('csv', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validCsv()
    {
        $complex = <<<'CSV'
,"""","foo""","\""",\,foo\
CSV;

        return [
            ['', [null]],
            [',', ['', '']],
            ['1', ['1']],
            ['1,2," 3 "', ['1', '2', ' 3 ']],
            ['\\,\\\\', ['\\', '\\\\']],
            [$complex, \PHP_VERSION_ID >= 70400 ? ['', '"', 'foo"', '\\"', '\\', 'foo\\'] : ['', '"', 'foo"', '\\"",\\,foo\\']],
            [null, null],
        ];
    }

    public function testEnvLoader()
    {
        $loaders = function () {
            yield new class() implements EnvVarLoaderInterface {
                public function loadEnvVars(): array
                {
                    return [
                        'FOO_ENV_LOADER' => '123',
                    ];
                }
            };

            yield new class() implements EnvVarLoaderInterface {
                public function loadEnvVars(): array
                {
                    return [
                        'FOO_ENV_LOADER' => '234',
                        'BAR_ENV_LOADER' => '456',
                    ];
                }
            };
        };

        $processor = new EnvVarProcessor(new Container(), $loaders());

        $result = $processor->getEnv('string', 'FOO_ENV_LOADER', function () {});
        $this->assertSame('123', $result);

        $result = $processor->getEnv('string', 'BAR_ENV_LOADER', function () {});
        $this->assertSame('456', $result);

        $result = $processor->getEnv('string', 'FOO_ENV_LOADER', function () {});
        $this->assertSame('123', $result); // check twice
    }

    public function testCircularEnvLoader()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(FOO_CONTAINER)', 'foo');
        $container->compile();

        $index = 0;
        $loaders = function () use (&$index) {
            if (0 === $index++) {
                throw new ParameterCircularReferenceException(['FOO_CONTAINER']);
            }

            yield new class() implements EnvVarLoaderInterface {
                public function loadEnvVars(): array
                {
                    return [
                        'FOO_ENV_LOADER' => '123',
                    ];
                }
            };
        };

        $processor = new EnvVarProcessor($container, new RewindableGenerator($loaders, 1));

        $result = $processor->getEnv('string', 'FOO_CONTAINER', function () {});
        $this->assertSame('foo', $result);

        $result = $processor->getEnv('string', 'FOO_ENV_LOADER', function () {});
        $this->assertSame('123', $result);

        $result = $processor->getEnv('default', ':BAR_CONTAINER', function ($name) use ($processor) {
            $this->assertSame('BAR_CONTAINER', $name);

            return $processor->getEnv('string', $name, function () {});
        });
        $this->assertNull($result);

        $this->assertSame(2, $index);
    }

    public function testGetEnvInvalidPrefixWithDefault()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported env var prefix');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('unknown', 'default::FAKE', function ($name) {
            $this->assertSame('default::FAKE', $name);

            return null;
        });
    }

    /**
     * @dataProvider provideGetEnvUrlPath
     */
    public function testGetEnvUrlPath(?string $expected, string $url)
    {
        $this->assertSame($expected, (new EnvVarProcessor(new Container()))->getEnv('url', 'foo', static function () use ($url): string {
            return $url;
        })['path']);
    }

    public function provideGetEnvUrlPath()
    {
        return [
            ['', 'https://symfony.com'],
            ['', 'https://symfony.com/'],
            ['/', 'https://symfony.com//'],
            ['blog', 'https://symfony.com/blog'],
            ['blog/', 'https://symfony.com/blog/'],
            ['blog//', 'https://symfony.com/blog//'],
        ];
    }
}
