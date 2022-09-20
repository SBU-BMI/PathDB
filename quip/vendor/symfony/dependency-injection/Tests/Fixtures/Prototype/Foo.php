<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;

class Foo implements FooInterface, Sub\BarInterface
{
    public function __construct($bar = null, iterable $foo = null, object $baz = null)
    {
    }

    public function setFoo(self $foo)
    {
    }
}
