<?php

namespace App\Exceptions;

use DomainException;

class PaymentNotCapturedException extends DomainException
{
    public static function forOrder(): self
    {
        return new self('Cannot refund a sub-order whose parent order has no captured payment.');
    }
}
