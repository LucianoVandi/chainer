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
     * @var array<int, MiddlewareInterface|callable|string>
     */
    private array $queue;

    private MiddlewareResolverInterface $resolver;
    private int $position = 0;

    /**
     * Costruttore di Relay.
     *
     * @param array<int, MiddlewareInterface|callable|string> $queue Coda di middleware.
     * @param MiddlewareResolverInterface|null $resolver Resolver opzionale per il middleware.
     *
     * @throws EmptyQueueException
     */
    public function __construct(array $queue, ?MiddlewareResolverInterface $resolver = null)
    {
        if (empty($queue)) {
            throw new EmptyQueueException();
        }

        $this->queue = array_values($queue);
        $this->resolver = $resolver ?? new DefaultResolver();
        $this->position = 0;
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
        if (empty($this->queue) || !array_key_exists($this->position, $this->queue)) {
            throw new NoRemainingMiddlewareException();
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
        $middleware = $this->queue[$this->position];

        $resolvedMiddleware = $this->resolver->resolve($middleware);

        $this->position++;

        return $resolvedMiddleware;
    }
}
