<?php

namespace App\Exceptions;

use App\Enums\RestaurantOrderStatus;
use DomainException;

class InvalidRestaurantOrderTransitionException extends DomainException
{
    public static function fromTo(RestaurantOrderStatus $from, RestaurantOrderStatus $to): self
    {
        return new self("A sub-order cannot transition from {$from->label()} to {$to->label()}.");
    }

    public static function notResumable(RestaurantOrderStatus $status): self
    {
        return new self("Only on-hold sub-orders can be resumed; this one is {$status->label()}.");
    }
}
