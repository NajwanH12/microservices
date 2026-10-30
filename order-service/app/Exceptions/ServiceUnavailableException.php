<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ServiceUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $service,
        string $reason = 'Service tidak dapat dihubungi',
        ?Throwable $previous = null,
    ) {
        parent::__construct($reason, previous: $previous);
    }
}
