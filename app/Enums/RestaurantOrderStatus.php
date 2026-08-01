<?php

namespace App\Enums;

enum RestaurantOrderStatus: string
{
    case Pending = 'pending';
    case PaymentReceived = 'payment-received';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case OnHold = 'on-hold';
    case Dispatched = 'dispatched';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially-refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PaymentReceived => 'Payment received',
            self::Accepted => 'Accepted',
            self::Preparing => 'Preparing',
            self::OnHold => 'On hold',
            self::Dispatched => 'Dispatched',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::PartiallyRefunded => 'Partially refunded',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Terminal statuses end the sub-order lifecycle; nothing leaves them.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::Refunded => true,
            default => false,
        };
    }

    /**
     * Whether the sub-order may move from this status to the given one.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Customers may cancel only until the restaurant starts preparing.
     */
    public function canBeCancelledByCustomer(): bool
    {
        return match ($this) {
            self::Pending, self::PaymentReceived, self::Accepted => true,
            default => false,
        };
    }

    /**
     * Only actively worked sub-orders can be paused.
     */
    public function canBePaused(): bool
    {
        return match ($this) {
            self::Accepted, self::Preparing => true,
            default => false,
        };
    }

    /**
     * Refunds require captured money: anything at or past payment
     * received that has not been fully refunded yet.
     */
    public function isRefundable(): bool
    {
        return match ($this) {
            self::PaymentReceived,
            self::Accepted,
            self::Preparing,
            self::OnHold,
            self::Dispatched,
            self::PartiallyRefunded => true,
            default => false,
        };
    }

    /**
     * The timestamp column stamped when entering this status, if any.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::PaymentReceived => 'placed_at',
            self::Accepted => 'accepted_at',
            self::Preparing => 'preparing_at',
            self::Dispatched => 'dispatched_at',
            self::Completed => 'completed_at',
            default => null,
        };
    }

    /**
     * The lifecycle as a transition map. Fulfilment flows
     * Pending → PaymentReceived → Accepted → Preparing → Dispatched →
     * Completed. OnHold is entered from Accepted/Preparing and resumes
     * back to them. Cancellation is possible from any non-dispatched,
     * non-terminal status. Refunds are possible from any paid,
     * non-terminal status; a partially refunded sub-order keeps moving
     * through fulfilment.
     *
     * @return array<int, self>
     */
    private function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::PaymentReceived, self::Cancelled],
            self::PaymentReceived => [self::Accepted, self::Cancelled, self::Refunded, self::PartiallyRefunded],
            self::Accepted => [self::Preparing, self::OnHold, self::Cancelled, self::Refunded, self::PartiallyRefunded],
            self::Preparing => [self::Dispatched, self::OnHold, self::Cancelled, self::Refunded, self::PartiallyRefunded],
            self::OnHold => [self::Accepted, self::Preparing, self::Cancelled, self::Refunded, self::PartiallyRefunded],
            self::Dispatched => [self::Completed, self::Refunded, self::PartiallyRefunded],
            self::Completed => [],
            self::PartiallyRefunded => [self::Dispatched, self::Completed, self::Cancelled, self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }
}
