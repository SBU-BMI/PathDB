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
use Symfony\Component\Validator\Constraints\DisableAutoMapping;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DisableAutoMappingTest extends TestCase
{
    public function testGroups()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage(sprintf('The option "groups" is not supported by the constraint "%s".', DisableAutoMapping::class));

        new DisableAutoMapping(['groups' => 'foo']);
    }
}
