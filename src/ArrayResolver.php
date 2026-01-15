<?php

namespace Lvandi\Chainer;

final class ArrayResolver implements MiddlewareResolverInterface
{
    private DefaultResolver $resolver;

    public function __construct()
    {
        $this->resolver = new DefaultResolver();
    }

    public function resolve($middleware): \Psr\Http\Server\MiddlewareInterface
    {
        return $this->resolver->resolve($middleware);
    }
}
