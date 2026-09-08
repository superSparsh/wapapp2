<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use RuntimeException;

class InvalidOtpException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Invalid OTP. Please try again.');
    }
}
