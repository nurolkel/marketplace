<?php

namespace App\Notifications;

use App\Enums\RestaurantOrderStatus;
use App\Models\RestaurantOrder;
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
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Order {$this->restaurantOrder->reference} update: {$this->to->label()}")
            ->line("Sub-order {$this->restaurantOrder->reference} changed from {$this->from->label()} to {$this->to->label()}.");

        if ($this->reason !== null) {
            $message->line("Reason: {$this->reason}");
        }

        return $message;
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
