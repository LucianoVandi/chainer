<?php

declare(strict_types=1);

namespace Lvandi\Chainer\Tests\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SpyMiddleware implements MiddlewareInterface
{
    private bool $called = false;

    public function wasCalled(): bool
    {
        return $this->called;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->called = true;

        return $handler->handle($request);
    }
}
