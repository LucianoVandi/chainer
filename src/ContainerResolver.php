<?php

declare(strict_types=1);

namespace Lvandi\Chainer;

use Lvandi\Chainer\Exception\InvalidMiddlewareException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

final class ContainerResolver implements MiddlewareResolverInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function resolve($middleware): MiddlewareInterface
    {
        if (! is_string($middleware)) {
            throw new InvalidMiddlewareException(
                'ContainerResolver expects a string middleware id.'
            );
        }

        if (! $this->container->has($middleware)) {
            throw new InvalidMiddlewareException(
                'Container has no entry for id "' . $middleware . '".'
            );
        }

        $resolved = $this->container->get($middleware);
        if (! $resolved instanceof MiddlewareInterface) {
            throw new InvalidMiddlewareException(
                'Container resolved an invalid middleware for id "' . $middleware . '".'
            );
        }

        return $resolved;
    }
}
