<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for all domain-level errors.
 *
 * Carries a structured {@see ErrorCode} that is included in JSON
 * error responses so that API consumers can react programmatically.
 *
 * Usage:
 *   throw DomainException::withCode(ErrorCode::CAMP_AUDIENCE_EMPTY, 'Audience has 0 contacts.');
 *   throw DomainException::withCode(ErrorCode::MSG_SEND_FAILED, details: ['phone' => '+91...']);
 */
class DomainException extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ?ErrorCode $errorCode = null,
        public readonly array $details = [],
        int $httpStatus = 0,
        ?Throwable $previous = null,
    ) {
        $this->httpStatus = $httpStatus ?: ($errorCode?->defaultHttpStatus() ?? 500);
        parent::__construct($message, $this->httpStatus, $previous);
    }

    protected int $httpStatus;

    /**
     * Create a domain exception with a structured error code.
     */
    public static function withCode(
        ErrorCode $code,
        string $message = '',
        array $details = [],
        ?Throwable $previous = null,
    ): static {
        return new static(
            message: $message ?: $code->name,
            errorCode: $code,
            details: $details,
            previous: $previous,
        );
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): ?ErrorCode
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
