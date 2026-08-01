<?php

namespace App\Exceptions;

use DomainException;

class RefundExceedsRemainingAmountException extends DomainException
{
    public static function create(int $amount, int $remaining): self
    {
        return new self("Cannot refund {$amount}; only {$remaining} remains refundable on this sub-order.");
    }
}
