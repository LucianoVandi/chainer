<?php

namespace Lvandi\Chainer;

use Psr\Http\Server\MiddlewareInterface;

interface MiddlewareResolverInterface
{
    /**
     * Risolve un middleware dall'identificativo fornito.
     *
     * @param mixed $middleware Identifier del middleware da risolvere.
     * @return MiddlewareInterface L'istanza middleware risolta.
     */
    public function resolve($middleware): MiddlewareInterface;
}
