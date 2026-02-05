# This is my package chainer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/luciano/chainer.svg?style=flat-square)](https://packagist.org/packages/luciano/chainer)
[![Tests](https://img.shields.io/github/actions/workflow/status/luciano/chainer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/luciano/chainer/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/luciano/chainer.svg?style=flat-square)](https://packagist.org/packages/luciano/chainer)

Chainer is a tiny PSR-15 middleware queue. It takes an ordered list of middleware and
executes them in sequence, acting as the request handler for the chain.

## Design Rationale

- Minimal API surface to keep the learning curve small.
- Strict types and explicit exceptions to make failures obvious.
- Resolver is pluggable so you can integrate containers or custom logic without coupling.
- Callable middleware support to showcase modern PHP and functional-style composition.

## Installation

You can install the package via composer:

```bash
composer require lvandi/chainer
```

Requirements: PHP 8.4+

## Usage

```php
use Lvandi\Chainer\Chainer;
use Lvandi\Chainer\DefaultResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HelloMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseInterface $response) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->response;
    }
}

$queue = [
    new HelloMiddleware($response),
];

$chainer = new Chainer($queue);
$result = $chainer->handle($request);
```

### Example: Two Middleware + Terminal

```php
$queue = [
    function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        return $handler->handle($request);
    },
    new HelloMiddleware($response),
];

$chainer = new Chainer($queue);
$result = $chainer->handle($request);
```

### Resolver

The default resolver supports:
- `MiddlewareInterface` instances
- `callable` middleware
- `class-string<MiddlewareInterface>` (optionally resolved via a PSR-11 container)

Example with a PSR-11 container:

```php
use Psr\Container\ContainerInterface;

$container = /* ContainerInterface */;
$resolver = new DefaultResolver($container);

$queue = [
    'app.middleware',
    SomeMiddleware::class,
    function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        return $handler->handle($request);
    },
];

$chainer = new Chainer($queue, $resolver);
```

### ContainerResolver + ResolverChain

If you want strict container-based resolution and explicit composition:

```php
use Lvandi\Chainer\ContainerResolver;
use Lvandi\Chainer\ResolverChain;

$container = /* ContainerInterface */;

$resolver = new ResolverChain([
    new ContainerResolver($container),
    new DefaultResolver(),
]);

$queue = [
    'app.middleware',
    SomeMiddleware::class,
];

$chainer = new Chainer($queue, $resolver);
```

## Testing

```bash
docker-compose run --rm test
```

Or with Makefile:

```bash
make test
```

## Code Coverage

Coverage via Docker:

```bash
docker-compose run --rm test composer test-coverage
```

Or with Makefile:

```bash
make coverage
```

## Error Handling

Chainer throws explicit exceptions to make failures obvious:
- `EmptyQueueException`: queue is empty at construction time.
- `NoRemainingMiddlewareException`: you called `handle()` after the queue is exhausted.
- `InvalidMiddlewareException`: a resolver returned a non-middleware or a callable returned a non-response.

Example:

```php
try {
    $chainer = new Chainer([]);
} catch (\Lvandi\Chainer\Exception\EmptyQueueException $e) {
    // Handle empty queue
}
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Credits

- [Luciano Vandi](https://github.com/LucianoVandi)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
