<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\RestaurantOrderStatus;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RestaurantOrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public RestaurantOrder $restaurantOrder,
        public RestaurantOrderStatus $from,
        public RestaurantOrderStatus $to,
        public ?string $reason = null,
    ) {}

    /**
     * Registered customers are reached on their preferred channels plus
     * the in-app database feed; anything else (on-demand guest mail)
     * falls back to plain mail.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $preference = $notifiable->notification_channel ?? NotificationChannel::Mail;

        return array_merge(
            $preference->channels($notifiable->phone !== null),
            ['database'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Order {$this->restaurantOrder->reference} update: {$this->to->label()}")
            ->line($this->statusLine());

        if ($this->reason !== null) {
            $message->line("Reason: {$this->reason}");
        }

        return $message;
    }

    /**
     * One short plain-text sentence per status for SMS delivery.
     */
    public function toSms(object $notifiable): string
    {
        $reference = $this->restaurantOrder->reference;

        return match ($this->to) {
            RestaurantOrderStatus::PaymentReceived => "Order {$reference} received — we've sent it to the restaurant.",
            RestaurantOrderStatus::Accepted => "Order {$reference} was accepted by the restaurant.",
            RestaurantOrderStatus::Preparing => "Order {$reference} is being prepared.",
            RestaurantOrderStatus::OnHold => "Order {$reference} was paused.",
            RestaurantOrderStatus::Dispatched => "Order {$reference} is on its way.",
            RestaurantOrderStatus::Completed => "Order {$reference} was delivered. Enjoy!",
            RestaurantOrderStatus::Cancelled => "Order {$reference} was cancelled.",
            RestaurantOrderStatus::PartiallyRefunded => "Order {$reference} was partially refunded.",
            RestaurantOrderStatus::Refunded => "Order {$reference} was refunded in full.",
            default => "Order {$reference} is now {$this->to->label()}.",
        };
    }

    private function statusLine(): string
    {
        return match ($this->to) {
            RestaurantOrderStatus::PaymentReceived => "We've received your order and sent it to the restaurant.",
            RestaurantOrderStatus::Accepted => 'The restaurant accepted your order.',
            RestaurantOrderStatus::Preparing => 'Your food is being prepared.',
            RestaurantOrderStatus::OnHold => 'Your order was paused.',
            RestaurantOrderStatus::Dispatched => 'Your order is on its way.',
            RestaurantOrderStatus::Completed => 'Delivered — enjoy!',
            RestaurantOrderStatus::Cancelled => 'Your order was cancelled.',
            RestaurantOrderStatus::PartiallyRefunded => 'Part of your order was refunded.',
            RestaurantOrderStatus::Refunded => 'Your order was refunded in full.',
            default => "Sub-order {$this->restaurantOrder->reference} changed from {$this->from->label()} to {$this->to->label()}.",
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'restaurant_order_id' => $this->restaurantOrder->id,
            'reference' => $this->restaurantOrder->reference,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'reason' => $this->reason,
        ];
    }
}
