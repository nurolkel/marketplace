<?php

namespace App\Enums;

enum RestaurantRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Staff => 'Staff',
        };
    }

    /**
     * Whether the role may change restaurant settings and profile details.
     */
    public function canManageRestaurant(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            self::Staff => false,
        };
    }

    /**
     * Whether the role may add, remove, or re-role staff members.
     */
    public function canManageStaff(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            self::Staff => false,
        };
    }

    /**
     * Whether the role may manage sub-orders: transitions, pauses,
     * cancellations, and refunds. All roles may for now; tightening
     * to owner/manager later only changes this one method.
     */
    public function canManageOrders(): bool
    {
        return match ($this) {
            self::Owner, self::Manager, self::Staff => true,
        };
    }
}
