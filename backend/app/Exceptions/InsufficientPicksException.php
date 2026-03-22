<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientPicksException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
