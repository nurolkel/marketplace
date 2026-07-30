<?php

namespace App\Enums;

enum RestaurantStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }
}
