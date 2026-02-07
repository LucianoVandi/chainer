<?php

declare(strict_types=1);

namespace Lvandi\Chainer\Tests\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class InvokableMiddleware implements MiddlewareInterface
{
    private static ?ResponseInterface $response = null;

    public static function setResponse(ResponseInterface $response): void
    {
        self::$response = $response;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (self::$response === null) {
            throw new \RuntimeException('Response not set.');
        }

        return self::$response;
    }
}
