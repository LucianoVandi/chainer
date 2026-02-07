<?php

declare(strict_types=1);

namespace Lvandi\Chainer;

use Lvandi\Chainer\Exception\EmptyQueueException;
use Lvandi\Chainer\Exception\InvalidMiddlewareException;
use Lvandi\Chainer\Exception\NoRemainingMiddlewareException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Chainer implements RequestHandlerInterface
{
    /**
     * @var array<int, array{name: string|null, middleware: MiddlewareInterface|callable|string, enabled: bool}>
     */
    private array $queue;

    private MiddlewareResolverInterface $resolver;
    private int $position = 0;

    /**
     * Costruttore di Relay.
     *
     * @param array<int|string, MiddlewareInterface|callable|string> $queue Coda di middleware.
     * @param MiddlewareResolverInterface|null $resolver Resolver opzionale per il middleware.
     *
     * @throws EmptyQueueException
     */
    public function __construct(array $queue, ?MiddlewareResolverInterface $resolver = null)
    {
        if (empty($queue)) {
            throw new EmptyQueueException('Middleware queue is empty. Provide at least one middleware.');
        }

        $this->queue = $this->normalizeQueue($queue);
        $this->resolver = $resolver ?? new DefaultResolver();
        $this->position = 0;
    }

    /**
     * Esegue un middleware solo se la condizione è vera.
     *
     * @param callable(ServerRequestInterface): bool $condition
     * @param MiddlewareInterface|callable|string $middleware
     */
    public function when(callable $condition, $middleware): self
    {
        return $this->add(new ConditionalMiddleware($condition, $middleware, $this->resolver, false));
    }

    /**
     * Esegue un middleware solo se la condizione è falsa.
     *
     * @param callable(ServerRequestInterface): bool $condition
     * @param MiddlewareInterface|callable|string $middleware
     */
    public function unless(callable $condition, $middleware): self
    {
        return $this->add(new ConditionalMiddleware($condition, $middleware, $this->resolver, true));
    }

    /**
     * Aggiunge un middleware (opzionalmente con nome).
     *
     * @param string|MiddlewareInterface|callable $nameOrMiddleware
     * @param MiddlewareInterface|callable|string|null $maybeMiddleware
     */
    public function add($nameOrMiddleware, $maybeMiddleware = null): self
    {
        if (is_string($nameOrMiddleware) && $maybeMiddleware !== null) {
            $this->assertNameAvailable($nameOrMiddleware);
            $this->queue[] = [
                'name' => $nameOrMiddleware,
                'middleware' => $maybeMiddleware,
                'enabled' => true,
            ];

            return $this;
        }

        $this->queue[] = [
            'name' => null,
            'middleware' => $nameOrMiddleware,
            'enabled' => true,
        ];

        return $this;
    }

    /**
     * Alias semantico di replace() per sostituire un middleware.
     *
     * @param MiddlewareInterface|callable|string $middleware
     */
    public function alias(string $name, $middleware): self
    {
        return $this->replace($name, $middleware);
    }

    /**
     * Disabilita un middleware per nome.
     */
    public function disable(string $name): self
    {
        foreach ($this->queue as $index => $entry) {
            if ($entry['name'] === $name) {
                $this->queue[$index]['enabled'] = false;

                return $this;
            }
        }

        throw new InvalidMiddlewareException('Middleware with name "' . $name . '" not found.');
    }

    /**
     * Abilita un middleware per nome.
     */
    public function enable(string $name): self
    {
        foreach ($this->queue as $index => $entry) {
            if ($entry['name'] === $name) {
                $this->queue[$index]['enabled'] = true;

                return $this;
            }
        }

        throw new InvalidMiddlewareException('Middleware with name "' . $name . '" not found.');
    }

    /**
     * Rimuove un middleware per nome.
     */
    public function remove(string $name): self
    {
        foreach ($this->queue as $index => $entry) {
            if ($entry['name'] === $name) {
                unset($this->queue[$index]);
                $this->queue = array_values($this->queue);
                if ($index < $this->position) {
                    $this->position--;
                }

                return $this;
            }
        }

        throw new InvalidMiddlewareException('Middleware with name "' . $name . '" not found.');
    }

    /**
     * Sostituisce un middleware per nome.
     *
     * @param MiddlewareInterface|callable|string $middleware
     */
    public function replace(string $name, $middleware): self
    {
        foreach ($this->queue as $index => $entry) {
            if ($entry['name'] === $name) {
                $this->queue[$index]['middleware'] = $middleware;

                return $this;
            }
        }

        throw new InvalidMiddlewareException('Middleware with name "' . $name . '" not found.');
    }

    /**
     * Verifica se esiste un middleware con nome.
     */
    public function has(string $name): bool
    {
        foreach ($this->queue as $entry) {
            if ($entry['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ritorna la lista completa dei middleware (con nomi).
     *
     * @return array<int, array{name: string|null, middleware: MiddlewareInterface|callable|string, enabled: bool}>
     */
    public function all(): array
    {
        return $this->queue;
    }

    /**
     * Ritorna una rappresentazione leggibile della pipeline.
     *
     * @return string[]
     */
    public function toArray(): array
    {
        $items = [];
        foreach ($this->queue as $entry) {
            if ($entry['name'] !== null) {
                $items[] = $entry['name'] . ($entry['enabled'] ? '' : ' (disabled)');

                continue;
            }

            $items[] = $this->describeMiddleware($entry['middleware']);
        }

        return $items;
    }

    /**
     * Alias di toArray() per debug.
     *
     * @return string[]
     */
    public function debug(): array
    {
        return $this->toArray();
    }

    /**
     * Gestisce la richiesta passando al prossimo middleware.
     *
     * @param ServerRequestInterface $request La richiesta server.
     *
     * @return ResponseInterface
     * @throws NoRemainingMiddlewareException|InvalidMiddlewareException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Verifica se la coda è esaurita o se non ci sono middleware disponibili.
        if (empty($this->queue) || ! array_key_exists($this->position, $this->queue)) {
            throw new NoRemainingMiddlewareException('No remaining middleware in the queue.');
        }

        // Continua l'elaborazione se c'è almeno un middleware nella coda.
        return $this->nextMiddleware()->process($request, $this);
    }

    /**
     * Ottiene il prossimo middleware dalla coda.
     *
     * @return MiddlewareInterface
     * @throws InvalidMiddlewareException
     */
    private function nextMiddleware(): MiddlewareInterface
    {
        while (array_key_exists($this->position, $this->queue)) {
            $entry = $this->queue[$this->position];
            $this->position++;

            if (! $entry['enabled']) {
                continue;
            }

            return $this->resolver->resolve($entry['middleware']);
        }

        throw new NoRemainingMiddlewareException('No remaining middleware in the queue.');
    }

    /**
     * @param array<int|string, MiddlewareInterface|callable|string> $queue
     * @return array<int, array{name: string|null, middleware: MiddlewareInterface|callable|string, enabled: bool}>
     */
    private function normalizeQueue(array $queue): array
    {
        $normalized = [];
        foreach ($queue as $name => $middleware) {
            if (is_string($name)) {
                $this->assertNameAvailable($name, $normalized);
                $normalized[] = [
                    'name' => $name,
                    'middleware' => $middleware,
                    'enabled' => true,
                ];

                continue;
            }

            $normalized[] = [
                'name' => null,
                'middleware' => $middleware,
                'enabled' => true,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array{name: string|null, middleware: MiddlewareInterface|callable|string, enabled: bool}>|null $queue
     */
    private function assertNameAvailable(string $name, ?array $queue = null): void
    {
        $haystack = $queue ?? $this->queue;
        foreach ($haystack as $entry) {
            if ($entry['name'] === $name) {
                throw new InvalidMiddlewareException('Middleware name "' . $name . '" already exists.');
            }
        }
    }

    /**
     * @param MiddlewareInterface|callable|string $middleware
     */
    private function describeMiddleware($middleware): string
    {
        if (is_string($middleware)) {
            return $middleware;
        }

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware::class;
        }

        if (is_array($middleware)) {
            return 'callable';
        }

        if ($middleware instanceof \Closure) {
            return 'closure';
        }

        return get_debug_type($middleware);
    }
}
