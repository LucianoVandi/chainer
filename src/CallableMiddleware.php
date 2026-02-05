<?php

declare(strict_types=1);

namespace Lvandi\Chainer;

use Lvandi\Chainer\Exception\InvalidMiddlewareException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CallableMiddleware implements MiddlewareInterface
{
    /**
     * @var callable(ServerRequestInterface, RequestHandlerInterface): mixed
     */
    private $callable;

    /**
     * @param callable(ServerRequestInterface, RequestHandlerInterface): mixed $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = ($this->callable)($request, $handler);
        /** @phpstan-ignore-next-line */
        if (! $response instanceof ResponseInterface) {
            throw new InvalidMiddlewareException(
                'Callable middleware must return a ' . ResponseInterface::class . '.'
            );
        }

        return $response;
    }
}
