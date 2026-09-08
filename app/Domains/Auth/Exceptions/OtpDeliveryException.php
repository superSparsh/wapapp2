<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use RuntimeException;

class OtpDeliveryException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Failed to send OTP. Please try again later.');
    }
}
