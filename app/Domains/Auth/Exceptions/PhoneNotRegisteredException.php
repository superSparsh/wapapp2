<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use RuntimeException;

class PhoneNotRegisteredException extends RuntimeException
{
    public static function make(): self
    {
        return new self('This phone number is not registered with any account.');
    }
}
