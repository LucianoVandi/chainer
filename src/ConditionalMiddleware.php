<?php

declare(strict_types=1);

namespace Lvandi\Chainer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ConditionalMiddleware implements MiddlewareInterface
{
    /**
     * @var callable(ServerRequestInterface): bool
     */
    private $condition;

    /**
     * @param callable(ServerRequestInterface): bool $condition
     * @param MiddlewareInterface|callable|string $middleware
     */
    public function __construct(
        callable $condition,
        private $middleware,
        private MiddlewareResolverInterface $resolver,
        private bool $negate
    ) {
        $this->condition = $condition;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = ($this->condition)($request);
        if ($this->negate) {
            $result = ! $result;
        }

        if (! $result) {
            return $handler->handle($request);
        }

        $resolved = $this->resolver->resolve($this->middleware);

        return $resolved->process($request, $handler);
    }
}
