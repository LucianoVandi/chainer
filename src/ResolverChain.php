<?php

declare(strict_types=1);

namespace Lvandi\Chainer;

use Lvandi\Chainer\Exception\InvalidMiddlewareException;
use Psr\Http\Server\MiddlewareInterface;

final class ResolverChain implements MiddlewareResolverInterface
{
    /**
     * @param MiddlewareResolverInterface[] $resolvers
     */
    public function __construct(private array $resolvers)
    {
    }

    public function resolve($middleware): MiddlewareInterface
    {
        foreach ($this->resolvers as $resolver) {
            try {
                return $resolver->resolve($middleware);
            } catch (InvalidMiddlewareException $exception) {
                continue;
            }
        }

        throw new InvalidMiddlewareException(
            'ResolverChain could not resolve middleware of type "' . get_debug_type($middleware) . '".'
        );
    }
}
