<?php

namespace Lvandi\Chainer\Exception;

use Exception;
use Throwable;

class EmptyQueueException extends Exception
{
    public function __construct(
        string $message = 'Middleware queue is empty.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
