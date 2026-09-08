<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use RuntimeException;

class OtpExpiredException extends RuntimeException
{
    public static function make(): self
    {
        return new self('OTP has expired. Please request a new one.');
    }
}
