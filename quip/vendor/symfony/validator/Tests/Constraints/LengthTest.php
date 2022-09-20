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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class LengthTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $length = new Length(['min' => 0, 'max' => 10, 'normalizer' => 'trim', 'allowEmptyString' => false]);

        $this->assertEquals('trim', $length->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Length(['min' => 0, 'max' => 10, 'normalizer' => 'Unknown Callable', 'allowEmptyString' => false]);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Length(['min' => 0, 'max' => 10, 'normalizer' => new \stdClass(), 'allowEmptyString' => false]);
    }
}
