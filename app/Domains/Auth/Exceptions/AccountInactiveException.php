<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use RuntimeException;

class AccountInactiveException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Your account is inactive. Please contact support.');
    }
}
