<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use RuntimeException;

class InvalidCredentialsException extends RuntimeException
{
    public static function make(): self
    {
        return new self('These credentials do not match our records.');
    }
}
