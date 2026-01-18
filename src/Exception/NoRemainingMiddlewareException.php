<?php

namespace Lvandi\Chainer\Exception;

use Exception;
use Throwable;

class NoRemainingMiddlewareException extends Exception
{
    public function __construct(
        string $message = 'No remaining middleware in the queue.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
