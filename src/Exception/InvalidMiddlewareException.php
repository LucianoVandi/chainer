<?php

namespace Lvandi\Chainer\Exception;

use Exception;
use Throwable;

class InvalidMiddlewareException extends Exception
{
    public function __construct(
        string $message = 'Invalid middleware.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
