<?php

declare(strict_types=1);

namespace Lvandi\Chainer\Tests;

use Lvandi\Chainer\Chainer;
use Lvandi\Chainer\DefaultResolver;
use Lvandi\Chainer\ArrayResolver;
use Lvandi\Chainer\Exception\EmptyQueueException;
use Lvandi\Chainer\Exception\InvalidMiddlewareException;
use Lvandi\Chainer\Exception\NoRemainingMiddlewareException;
use Lvandi\Chainer\ContainerResolver;
use Lvandi\Chainer\ResolverChain;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ChainerTest extends TestCase
{
    public function test_constructor_throws_on_empty_queue(): void
    {
        $this->expectException(EmptyQueueException::class);

        new Chainer([]);
    }

    public function test_handle_returns_response_from_middleware(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);

        $chainer = new Chainer([new TerminalMiddleware($response)]);

        $this->assertSame($response, $chainer->handle($request));
    }

    public function test_handle_throws_when_queue_is_exhausted(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);

        $chainer = new Chainer([new TerminalMiddleware($response)]);

        $chainer->handle($request);

        $this->expectException(NoRemainingMiddlewareException::class);
        $chainer->handle($request);
    }

    public function test_handle_throws_on_invalid_middleware(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        /** @phpstan-ignore-next-line */
        $chainer = new Chainer([new \stdClass()]);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->handle($request);
    }

    public function test_callable_middleware_is_supported(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);

        $callable = function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($response): ResponseInterface {
            return $response;
        };

        $chainer = new Chainer([$callable]);

        $this->assertSame($response, $chainer->handle($request));
    }

    public function test_callable_middleware_must_return_response(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $callable = function (ServerRequestInterface $request, RequestHandlerInterface $handler): string {
            return 'not-a-response';
        };

        $chainer = new Chainer([$callable]);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->handle($request);
    }

    public function test_class_string_middleware_is_instantiated(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);

        $chainer = new Chainer([InvokableMiddleware::class]);
        InvokableMiddleware::setResponse($response);

        $this->assertSame($response, $chainer->handle($request));
    }

    public function test_container_is_used_when_available(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $middleware = new TerminalMiddleware($response);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('middleware.id')->willReturn(true);
        $container->method('get')->with('middleware.id')->willReturn($middleware);

        $resolver = new DefaultResolver($container);
        $chainer = new Chainer(['middleware.id'], $resolver);

        $this->assertSame($response, $chainer->handle($request));
    }

    public function test_container_must_return_middleware(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('bad.middleware')->willReturn(true);
        $container->method('get')->with('bad.middleware')->willReturn(new \stdClass());

        $resolver = new DefaultResolver($container);
        $chainer = new Chainer(['bad.middleware'], $resolver);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->handle($request);
    }

    public function test_container_resolver_requires_string_id(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $container = $this->createMock(ContainerInterface::class);

        $resolver = new ContainerResolver($container);
        $chainer = new Chainer([new \stdClass()], $resolver);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->handle($request);
    }

    public function test_container_resolver_requires_existing_id(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('missing')->willReturn(false);

        $resolver = new ContainerResolver($container);
        $chainer = new Chainer(['missing'], $resolver);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->handle($request);
    }

    public function test_resolver_chain_uses_first_matching_resolver(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $middleware = new TerminalMiddleware($response);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('middleware.id')->willReturn(true);
        $container->method('get')->with('middleware.id')->willReturn($middleware);

        $resolver = new ResolverChain([
            new ContainerResolver($container),
            new DefaultResolver(),
        ]);

        $chainer = new Chainer(['middleware.id'], $resolver);

        $this->assertSame($response, $chainer->handle($request));
    }

    public function test_resolver_chain_throws_if_none_can_resolve(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $resolver = new ResolverChain([
            new DefaultResolver(),
        ]);

        $chainer = new Chainer([new \stdClass()], $resolver);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->handle($request);
    }

    public function test_invalid_class_string_is_rejected(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $chainer = new Chainer([\stdClass::class]);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->handle($request);
    }

    public function test_array_resolver_is_supported(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);

        $resolver = new ArrayResolver();
        $chainer = new Chainer([new TerminalMiddleware($response)], $resolver);

        $this->assertSame($response, $chainer->handle($request));
    }

}

final class TerminalMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseInterface $response)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->response;
    }
}

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
