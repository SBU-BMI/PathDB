<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Finder\Finder;

$polyfillsBootstraps = \array_map(
    function (SplFileInfo $fileInfo) {
        return $fileInfo->getPathname();
    },
    \iterator_to_array(
        Finder::create()
            ->files()
            ->in(__DIR__.'/vendor/symfony/polyfill-*')
            ->name('bootstrap.php'),
        false
    )
 );

$polyfillsStubs = [];

try {
    $polyfillsStubs = \array_map(
        function (SplFileInfo $fileInfo) {
            return $fileInfo->getPathname();
        },
        \iterator_to_array(
            Finder::create()
                ->files()
                ->in(__DIR__.'/vendor/symfony/polyfill-*/Resources/stubs')
                ->name('*.php'),
            false
        )
    );
} catch (Throwable $e) {
    // There may not be any stubs?
}

return [
    'whitelist' => [
        'Psy\*',
        'Symfony\Polyfill\*',

        // Old Hoa global functions
        'from',
        'dnew',
        'xcallable',
        'curry',
        'curry_ref',
    ],

    'files-whitelist' => \array_merge($polyfillsBootstraps, $polyfillsStubs),

    'patchers' => [
        // Un-patch overly enthusiastic internal constant patching.
        // https://github.com/humbug/php-scoper/issues/356
        static function (string $filePath, string $prefix, string $contents): string {
            if ('src/Reflection/ReflectionClassConstant.php' !== $filePath) {
                return $contents;
            }

            return \str_replace(
                \sprintf("'%s\\\\ReflectionClassConstant'", $prefix),
                "'\\\\ReflectionClassConstant'",
                $contents
            );
        },
        // https://github.com/bobthecow/psysh/issues/610
        static function (string $filePath, string $prefix, string $contents): string {
            if (!\in_array($filePath, ['vendor/symfony/var-dumper/Cloner/VarCloner.php', 'vendor/symfony/var-dumper/Caster/ReflectionCaster.php'], true)) {
                return $contents;
            }

            return \str_replace(
                \sprintf('\\%s\\ReflectionReference', $prefix),
                '\\ReflectionReference',
                $contents
            );
        },
        // Hoa patches
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/hoa/stream/Stream.php' !== $filePath) {
                return $contents;
            }

            return \preg_replace(
                '/Hoa\\\\Consistency::registerShutdownFunction\(xcallable\(\'(.*)\'\)\)/',
                \sprintf(
                    'Hoa\\Consistency::registerShutdownFunction(xcallable(\'%s$1\'))',
                    $prefix.'\\\\\\\\'
                ),
                $contents
            );
        },
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/hoa/consistency/Autoloader.php' !== $filePath) {
                return $contents;
            }
            $contents = \preg_replace(
                '/(\$entityPrefix = \$entity;)/',
                \sprintf(
                    '$entity = substr($entity, %d);$1',
                    \strlen($prefix) + 1
                ),
                $contents
            );
            $contents = \preg_replace(
                '/return \$this->runAutoloaderStack\((.*)\);/',
                \sprintf(
                    'return $this->runAutoloaderStack(\'%s\'.\'%s\'.$1);',
                    $prefix,
                    '\\\\\\'
                ),
                $contents
            );

            return $contents;
        },
        static function (string $filePath, string $prefix, string $contents): string {
            if (!\in_array($filePath, ['vendor/hoa/console/Mouse.php', 'vendor/hoa/console/Console.php', 'vendor/hoa/core/Consistency.php'], true)) {
                return $contents;
            }

            return \preg_replace(
                '/\'(?:\\\\){0,2}(Hoa\\\\.+?)(::.+)\'/',
                \sprintf(
                    '\'%s\\\\\\\$1$2\'',
                    $prefix
                ),
                $contents
            );
        },
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/hoa/core/Consistency.php' === $filePath) {
                return $contents;
            }

            return \str_replace(
                '$classname = \ltrim($classname, \'\\\\\');',
                \sprintf(
                    '$classname = \substr(\ltrim($classname, \'\\\\\'), %d);',
                    \strlen($prefix) + 1
                ),
                $contents
            );
        },
        // https://github.com/humbug/php-scoper/issues/294
        // https://github.com/humbug/php-scoper/issues/286
        static function (string $filePath, string $prefix, string $contents): string {
            if (!\in_array($filePath, ['src/Formatter/DocblockFormatter.php', 'src/Output/ShellOutput.php'], true)) {
                return $contents;
            }

            return \str_replace(
                '\'Symfony\\\\Component\\\\Console\\\\Formatter\\\\OutputFormatter\'',
                \sprintf(
                    '\'%s\\%s\'',
                    $prefix,
                    'Symfony\\Component\\Console\\Formatter\\OutputFormatter'
                ),
                $contents
            );
        },
        // Symfony patches
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/symfony/debug/DebugClassLoader.php' !== $filePath) {
                return $contents;
            }

            return \preg_replace(
                '/case \'(Symfony\\\\.+\\\\)\':/',
                \sprintf(
                    'case \'%s\\\\\\\$1\':',
                    $prefix
                ),
                $contents
            );
        },
        // https://github.com/humbug/php-scoper/issues/286
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/symfony/var-dumper/Cloner/AbstractCloner.php' !== $filePath) {
                return $contents;
            }

            return \preg_replace(
                '/\'(Symfony\\\\.+?)\'/',
                \sprintf(
                    '\'%s\\\\\\\$1\'',
                    $prefix
                ),
                $contents
            );
        },
        // https://github.com/humbug/php-scoper/issues/286
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/symfony/debug/Exception/FlattenException.php' !== $filePath) {
                return $contents;
            }

            return \preg_replace(
                '/\'(Symfony\\\\.+?)\'/',
                \sprintf(
                    '\'%s\\\\\\\$1\'',
                    $prefix
                ),
                $contents
            );
        },
        // PHP-Parser patches
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/nikic/php-parser/lib/PhpParser/JsonDecoder.php' !== $filePath) {
                return $contents;
            }

            return \str_replace(
                '\'PhpParser\\\\Node\\\\\'',
                \sprintf(
                    '\'%s\\\\PhpParser\\\\Node\\\\\'',
                    $prefix
                ),
                $contents
            );
        },
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/nikic/php-parser/lib/PhpParser/Unserializer/XML.php' !== $filePath) {
                return $contents;
            }

            $contents = \preg_replace(
                '/\'(PhpParser\\\\.+(?:\\\\)?)\'/',
                \sprintf(
                    '\'%s\\\\\\\$1\'',
                    $prefix
                ),
                $contents
            );

            $contents = \preg_replace(
                '/\'(PhpParser\\\\\\\\\p{L}+)(?:\\\\\\\\)?\'/u',
                \sprintf(
                    '\'%s\\\\\\\$1\'',
                    $prefix
                ),
                $contents
            );

            return $contents;
        },
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/nikic/php-parser/lib/PhpParser/Lexer.php' !== $filePath) {
                return $contents;
            }

            return \str_replace(
                '\'PhpParser\\\\Parser\\\\Tokens::\'',
                \sprintf(
                    '\'%s\\\\PhpParser\\\\Parser\\\\Tokens::\'',
                    $prefix
                ),
                $contents
            );
        },
    ],
];
