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
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Tests\Fixtures\CustomArrayObject;
use Symfony\Component\Validator\Tests\Fixtures\ToString;

class ConstraintViolationTest extends TestCase
{
    public function testToStringHandlesArrays()
    {
        $violation = new ConstraintViolation(
            'Array',
            '{{ value }}',
            ['{{ value }}' => [1, 2, 3]],
            'Root',
            'property.path',
            null
        );

        $expected = <<<'EOF'
Root.property.path:
    Array
EOF;

        $this->assertSame($expected, (string) $violation);
    }

    public function testToStringHandlesArrayRoots()
    {
        $violation = new ConstraintViolation(
            '42 cannot be used here',
            'this is the message template',
            [],
            ['some_value' => 42],
            'some_value',
            null
        );

        $expected = <<<'EOF'
Array.some_value:
    42 cannot be used here
EOF;

        $this->assertSame($expected, (string) $violation);
    }

    public function testToStringHandlesCodes()
    {
        $violation = new ConstraintViolation(
            '42 cannot be used here',
            'this is the message template',
            [],
            ['some_value' => 42],
            'some_value',
            null,
            null,
            '0'
        );

        $expected = <<<'EOF'
Array.some_value:
    42 cannot be used here (code 0)
EOF;

        $this->assertSame($expected, (string) $violation);
    }

    public function testToStringOmitsEmptyCodes()
    {
        $expected = <<<'EOF'
Array.some_value:
    42 cannot be used here
EOF;

        $violation = new ConstraintViolation(
            '42 cannot be used here',
            'this is the message template',
            [],
            ['some_value' => 42],
            'some_value',
            null,
            null,
            null
        );

        $this->assertSame($expected, (string) $violation);

        $violation = new ConstraintViolation(
            '42 cannot be used here',
            'this is the message template',
            [],
            ['some_value' => 42],
            'some_value',
            null,
            null,
            ''
        );

        $this->assertSame($expected, (string) $violation);
    }

    public function testMessageCanBeStringableObject()
    {
        $message = new ToString();
        $violation = new ConstraintViolation(
            $message,
            (string) $message,
            [],
            'Root',
            'property.path',
            null
        );

        $expected = <<<'EOF'
Root.property.path:
    toString
EOF;
        $this->assertSame($expected, (string) $violation);
        $this->assertSame($message, $violation->getMessage());
    }

    public function testMessageCannotBeArray()
    {
        $this->expectException(\TypeError::class);
        new ConstraintViolation(
            ['cannot be an array'],
            '',
            [],
            'Root',
            'property.path',
            null
        );
    }

    public function testMessageObjectMustBeStringable()
    {
        $this->expectException(\TypeError::class);
        new ConstraintViolation(
            new CustomArrayObject(),
            '',
            [],
            'Root',
            'property.path',
            null
        );
    }

    /**
     * @group legacy
     * @expectedDeprecation Not using a string as the error code in Symfony\Component\Validator\ConstraintViolation::__construct() is deprecated since Symfony 4.4. A type-hint will be added in 5.0.
     */
    public function testNonStringCode()
    {
        $violation = new ConstraintViolation(
            '42 cannot be used here',
            'this is the message template',
            [],
            ['some_value' => 42],
            'some_value',
            null,
            null,
            42
        );

        self::assertSame(42, $violation->getCode());
    }
}
