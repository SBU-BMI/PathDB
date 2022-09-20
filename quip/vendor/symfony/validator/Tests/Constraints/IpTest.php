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
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class IpTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $ip = new Ip(['normalizer' => 'trim']);

        $this->assertEquals('trim', $ip->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Ip(['normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Ip(['normalizer' => new \stdClass()]);
    }
}
