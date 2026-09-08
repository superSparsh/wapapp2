<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\ErrorCode;

/**
 * Thrown when a circuit breaker is open and calls are being blocked.
 */
class CircuitOpenException extends DomainException
{
    public function __construct(string $message = 'External service temporarily unavailable.')
    {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::SYS_CIRCUIT_OPEN,
        );
    }
}
