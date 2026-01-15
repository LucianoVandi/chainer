<?php

declare(strict_types=1);

namespace Lvandi\Chainer;

use Lvandi\Chainer\Exception\InvalidMiddlewareException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

final class DefaultResolver implements MiddlewareResolverInterface
{
    public function __construct(private ?ContainerInterface $container = null)
    {
    }

    public function resolve($middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_callable($middleware)) {
            return new CallableMiddleware($middleware);
        }

        if (is_string($middleware)) {
            if ($this->container !== null && $this->container->has($middleware)) {
                $resolved = $this->container->get($middleware);
                if ($resolved instanceof MiddlewareInterface) {
                    return $resolved;
                }
                throw new InvalidMiddlewareException();
            }

            if (class_exists($middleware)) {
                if (!is_subclass_of($middleware, MiddlewareInterface::class)) {
                    throw new InvalidMiddlewareException();
                }

                return new $middleware();
            }
        }

        throw new InvalidMiddlewareException();
    }
}
