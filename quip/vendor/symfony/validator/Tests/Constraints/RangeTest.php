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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

class RangeTest extends TestCase
{
    public function testThrowsConstraintExceptionIfBothMinLimitAndPropertyPath()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('requires only one of the "min" or "minPropertyPath" options to be set, not both.');
        new Range([
            'min' => 'min',
            'minPropertyPath' => 'minPropertyPath',
        ]);
    }

    public function testThrowsConstraintExceptionIfBothMaxLimitAndPropertyPath()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('requires only one of the "max" or "maxPropertyPath" options to be set, not both.');
        new Range([
            'max' => 'max',
            'maxPropertyPath' => 'maxPropertyPath',
        ]);
    }

    public function testThrowsConstraintExceptionIfNoLimitNorPropertyPath()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('Either option "min", "minPropertyPath", "max" or "maxPropertyPath" must be given');
        new Range([]);
    }

    public function testThrowsNoDefaultOptionConfiguredException()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('No default option is configured');
        new Range('value');
    }

    public function provideDeprecationTriggeredIfMinMaxAndMinMessageOrMaxMessageSet(): array
    {
        return [
            [['min' => 1, 'max' => 10, 'minMessage' => 'my_min_message'], true, false],
            [['min' => 1, 'max' => 10, 'maxMessage' => 'my_max_message'], false, true],
            [['min' => 1, 'max' => 10, 'minMessage' => 'my_min_message', 'maxMessage' => 'my_max_message'], true, true],
        ];
    }

    /**
     * @group legacy
     * @expectedDeprecation Since symfony/validator 4.4: "minMessage" and "maxMessage" are deprecated when the "min" and "max" options are both set. Use "notInRangeMessage" instead.
     * @dataProvider provideDeprecationTriggeredIfMinMaxAndMinMessageOrMaxMessageSet
     */
    public function testDeprecationTriggeredIfMinMaxAndMinMessageOrMaxMessageSet(array $options, bool $expectedDeprecatedMinMessageSet, bool $expectedDeprecatedMaxMessageSet)
    {
        $sut = new Range($options);
        $this->assertEquals($expectedDeprecatedMinMessageSet, $sut->deprecatedMinMessageSet);
        $this->assertEquals($expectedDeprecatedMaxMessageSet, $sut->deprecatedMaxMessageSet);
    }

    public function provideDeprecationNotTriggeredIfNotMinMaxOrNotMinMessageNorMaxMessageSet(): array
    {
        return [
            [['min' => 1, 'minMessage' => 'my_min_message', 'maxMessage' => 'my_max_message']],
            [['max' => 10, 'minMessage' => 'my_min_message', 'maxMessage' => 'my_max_message']],
            [['min' => 1, 'max' => 10, 'notInRangeMessage' => 'my_message']],
        ];
    }

    /**
     * @doesNotPerformAssertions
     * @dataProvider provideDeprecationNotTriggeredIfNotMinMaxOrNotMinMessageNorMaxMessageSet
     */
    public function testDeprecationNotTriggeredIfNotMinMaxOrNotMinMessageNorMaxMessageSet(array $options)
    {
        new Range($options);
    }
}
