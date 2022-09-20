<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LengthValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class LengthValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new LengthValidator();
    }

    public function testLegacyNullIsValid()
    {
        $this->validator->validate(null, new Length(['value' => 6, 'allowEmptyString' => false]));

        $this->assertNoViolation();
    }

    /**
     * @group legacy
     * @expectedDeprecation Using the "Symfony\Component\Validator\Constraints\Length" constraint with the "min" option without setting the "allowEmptyString" one is deprecated and defaults to true. In 5.0, it will become optional and default to false.
     */
    public function testLegacyEmptyStringIsValid()
    {
        $this->validator->validate('', new Length(6));

        $this->assertNoViolation();
    }

    public function testEmptyStringIsInvalid()
    {
        $this->validator->validate('', new Length([
            'value' => $limit = 6,
            'allowEmptyString' => false,
            'exactMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '""')
            ->setParameter('{{ limit }}', $limit)
            ->setInvalidValue('')
            ->setPlural($limit)
            ->setCode(Length::TOO_SHORT_ERROR)
            ->assertRaised();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Length(['value' => 5, 'allowEmptyString' => false]));
    }

    public function getThreeOrLessCharacters()
    {
        return [
            [12],
            ['12'],
            ['üü'],
            ['éé'],
            [123],
            ['123'],
            ['üüü'],
            ['ééé'],
        ];
    }

    public function getFourCharacters()
    {
        return [
            [1234],
            ['1234'],
            ['üüüü'],
            ['éééé'],
        ];
    }

    public function getFiveOrMoreCharacters()
    {
        return [
            [12345],
            ['12345'],
            ['üüüüü'],
            ['ééééé'],
            [123456],
            ['123456'],
            ['üüüüüü'],
            ['éééééé'],
        ];
    }

    public function getOneCharset()
    {
        return [
            ['é', 'utf8', true],
            ["\xE9", 'CP1252', true],
            ["\xE9", 'XXX', false],
            ["\xE9", 'utf8', false],
        ];
    }

    public function getThreeCharactersWithWhitespaces()
    {
        return [
            ["\x20ccc"],
            ["\x09c\x09c"],
            ["\x0Accc\x0A"],
            ["ccc\x0D\x0D"],
            ["\x00ccc\x00"],
            ["\x0Bc\x0Bc\x0B"],
        ];
    }

    /**
     * @dataProvider getFiveOrMoreCharacters
     */
    public function testValidValuesMin($value)
    {
        $constraint = new Length(['min' => 5, 'allowEmptyString' => false]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getThreeOrLessCharacters
     */
    public function testValidValuesMax($value)
    {
        $constraint = new Length(['max' => 3]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getFourCharacters
     */
    public function testValidValuesExact($value)
    {
        $constraint = new Length(['value' => 4, 'allowEmptyString' => false]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getThreeCharactersWithWhitespaces
     */
    public function testValidNormalizedValues($value)
    {
        $constraint = new Length(['min' => 3, 'max' => 3, 'normalizer' => 'trim', 'allowEmptyString' => false]);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getThreeOrLessCharacters
     */
    public function testInvalidValuesMin($value)
    {
        $constraint = new Length([
            'min' => 4,
            'minMessage' => 'myMessage',
            'allowEmptyString' => false,
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setParameter('{{ limit }}', 4)
            ->setInvalidValue($value)
            ->setPlural(4)
            ->setCode(Length::TOO_SHORT_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getFiveOrMoreCharacters
     */
    public function testInvalidValuesMax($value)
    {
        $constraint = new Length([
            'max' => 4,
            'maxMessage' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setParameter('{{ limit }}', 4)
            ->setInvalidValue($value)
            ->setPlural(4)
            ->setCode(Length::TOO_LONG_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getThreeOrLessCharacters
     */
    public function testInvalidValuesExactLessThanFour($value)
    {
        $constraint = new Length([
            'min' => 4,
            'max' => 4,
            'exactMessage' => 'myMessage',
            'allowEmptyString' => false,
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setParameter('{{ limit }}', 4)
            ->setInvalidValue($value)
            ->setPlural(4)
            ->setCode(Length::TOO_SHORT_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getFiveOrMoreCharacters
     */
    public function testInvalidValuesExactMoreThanFour($value)
    {
        $constraint = new Length([
            'min' => 4,
            'max' => 4,
            'exactMessage' => 'myMessage',
            'allowEmptyString' => false,
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setParameter('{{ limit }}', 4)
            ->setInvalidValue($value)
            ->setPlural(4)
            ->setCode(Length::TOO_LONG_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getOneCharset
     */
    public function testOneCharset($value, $charset, $isValid)
    {
        $constraint = new Length([
            'min' => 1,
            'max' => 1,
            'charset' => $charset,
            'charsetMessage' => 'myMessage',
            'allowEmptyString' => false,
        ]);

        $this->validator->validate($value, $constraint);

        if ($isValid) {
            $this->assertNoViolation();
        } else {
            $this->buildViolation('myMessage')
                ->setParameter('{{ value }}', '"'.$value.'"')
                ->setParameter('{{ charset }}', $charset)
                ->setInvalidValue($value)
                ->setCode(Length::INVALID_CHARACTERS_ERROR)
                ->assertRaised();
        }
    }

    public function testConstraintDefaultOption()
    {
        $constraint = new Length(['value' => 5, 'allowEmptyString' => false]);

        $this->assertEquals(5, $constraint->min);
        $this->assertEquals(5, $constraint->max);
    }

    public function testConstraintAnnotationDefaultOption()
    {
        $constraint = new Length(['value' => 5, 'exactMessage' => 'message', 'allowEmptyString' => false]);

        $this->assertEquals(5, $constraint->min);
        $this->assertEquals(5, $constraint->max);
        $this->assertEquals('message', $constraint->exactMessage);
    }
}
