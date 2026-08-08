<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    /**
     * Deliver the notification as an SMS. Logged instead of sent for
     * now — a real provider (Twilio, Vonage) can replace the log line
     * later without touching the notifications themselves.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms()
            : $notifiable->phone ?? null;

        if (! is_string($phone) || $phone === '') {
            return;
        }

        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);

        Log::info("SMS to {$phone}: {$message}");
    }
}
