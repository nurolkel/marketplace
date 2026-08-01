<?php

namespace App\Exceptions;

use DomainException;

class PaymentRefundFailedException extends DomainException
{
    public static function withReason(?string $reason): self
    {
        return new self('The payment driver rejected the refund.'.($reason ? " {$reason}" : ''));
    }
}
