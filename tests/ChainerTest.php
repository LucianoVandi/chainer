<?php

declare(strict_types=1);

namespace Lvandi\Chainer\Tests;

use Lvandi\Chainer\ArrayResolver;
use Lvandi\Chainer\Chainer;
use Lvandi\Chainer\ContainerResolver;
use Lvandi\Chainer\DefaultResolver;
use Lvandi\Chainer\Exception\EmptyQueueException;
use Lvandi\Chainer\Exception\InvalidMiddlewareException;
use Lvandi\Chainer\Exception\NoRemainingMiddlewareException;
use Lvandi\Chainer\ResolverChain;
use Lvandi\Chainer\Tests\Support\CallableArrayTarget;
use Lvandi\Chainer\Tests\Support\InvokableMiddleware;
use Lvandi\Chainer\Tests\Support\PassThroughMiddleware;
use Lvandi\Chainer\Tests\Support\SpyMiddleware;
use Lvandi\Chainer\Tests\Support\TerminalMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
        /** @phpstan-ignore-next-line */
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

        /** @phpstan-ignore-next-line */
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

    public function test_when_executes_middleware_when_condition_is_true(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $spy = new SpyMiddleware();
        $chainer = new Chainer([new PassThroughMiddleware()]);
        $chainer->when(fn (ServerRequestInterface $request): bool => true, $spy);
        $chainer->add(new TerminalMiddleware($response));

        $this->assertSame($response, $chainer->handle($request));
        $this->assertTrue($spy->wasCalled());
    }

    public function test_when_skips_middleware_when_condition_is_false(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $spy = new SpyMiddleware();
        $chainer = new Chainer([new PassThroughMiddleware()]);
        $chainer->when(fn (ServerRequestInterface $request): bool => false, $spy);
        $chainer->add(new TerminalMiddleware($response));

        $this->assertSame($response, $chainer->handle($request));
        $this->assertFalse($spy->wasCalled());
    }

    public function test_unless_executes_middleware_when_condition_is_false(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $spy = new SpyMiddleware();
        $chainer = new Chainer([new PassThroughMiddleware()]);
        $chainer->unless(fn (ServerRequestInterface $request): bool => false, $spy);
        $chainer->add(new TerminalMiddleware($response));

        $this->assertSame($response, $chainer->handle($request));
        $this->assertTrue($spy->wasCalled());
    }

    public function test_unless_skips_middleware_when_condition_is_true(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $spy = new SpyMiddleware();
        $chainer = new Chainer([new PassThroughMiddleware()]);
        $chainer->unless(fn (ServerRequestInterface $request): bool => true, $spy);
        $chainer->add(new TerminalMiddleware($response));

        $this->assertSame($response, $chainer->handle($request));
        $this->assertFalse($spy->wasCalled());
    }

    public function test_named_middleware_operations(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);

        $chainer = new Chainer(['auth' => new TerminalMiddleware($response)]);

        $this->assertTrue($chainer->has('auth'));
        $this->assertSame(['auth'], $chainer->toArray());
        $this->assertCount(1, $chainer->all());

        $chainer->add('rate_limit', new PassThroughMiddleware());
        $this->assertSame(['auth', 'rate_limit'], $chainer->debug());
        $this->assertCount(2, $chainer->all());

        $replacement = new TerminalMiddleware($response);
        $chainer->replace('auth', $replacement);
        $this->assertSame($response, $chainer->handle($request));

        $chainer->remove('rate_limit');
        $this->assertSame(['auth'], $chainer->toArray());
    }

    public function test_duplicate_named_middleware_throws(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $chainer = new Chainer(['auth' => new TerminalMiddleware($response)]);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->add('auth', new PassThroughMiddleware());
    }

    public function test_remove_unknown_name_throws(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $chainer = new Chainer([new TerminalMiddleware($response)]);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->remove('missing');
    }

    public function test_replace_unknown_name_throws(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $chainer = new Chainer([new TerminalMiddleware($response)]);

        $this->expectException(InvalidMiddlewareException::class);
        $chainer->replace('missing', new PassThroughMiddleware());
    }

    public function test_to_array_describes_unnamed_middleware(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $callableArray = [new CallableArrayTarget(), 'handle'];
        $closure = function (): void {
        };

        $chainer = new Chainer([
            InvokableMiddleware::class,
            new TerminalMiddleware($response),
            $callableArray,
            $closure,
        ]);

        $this->assertSame([
            InvokableMiddleware::class,
            TerminalMiddleware::class,
            'callable',
            'closure',
        ], $chainer->toArray());
    }

    public function test_debug_is_alias_of_to_array(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $chainer = new Chainer([new TerminalMiddleware($response)]);

        $this->assertSame($chainer->toArray(), $chainer->debug());
    }

    public function test_add_accepts_string_middleware_and_unknown_types_for_debug(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $chainer = new Chainer([new TerminalMiddleware($response)]);
        $chainer->add('custom.middleware');
        /** @phpstan-ignore-next-line */
        $chainer->add(new \stdClass());

        $this->assertSame([
            TerminalMiddleware::class,
            'custom.middleware',
            \stdClass::class,
        ], $chainer->toArray());
    }

    public function test_has_returns_false_when_missing(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $chainer = new Chainer(['auth' => new TerminalMiddleware($response)]);

        $this->assertFalse($chainer->has('missing'));
    }

    public function test_remove_adjusts_position_when_removing_processed_middleware(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);

        $chainer = new Chainer([
            'first' => new PassThroughMiddleware(),
            'second' => new TerminalMiddleware($response),
        ]);

        $this->assertSame($response, $chainer->handle($request));

        $chainer->remove('first');
        $this->assertSame(['second'], $chainer->toArray());
    }
}
