<?php

namespace App\Exceptions;

use DomainException;

class CannotRemoveLastOwnerException extends DomainException
{
    public static function forRestaurant(): self
    {
        return new self('A restaurant must always have at least one owner.');
    }
}
