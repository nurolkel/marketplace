<?php

namespace App\Enums;

use App\Notifications\Channels\SmsChannel;

enum NotificationChannel: string
{
    case Mail = 'mail';
    case Sms = 'sms';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::Sms => 'SMS',
            self::Both => 'Email & SMS',
        };
    }

    /**
     * The notification channels the preference resolves to. SMS needs a
     * phone number; without one, every SMS entry degrades to mail (so
     * SMS-only becomes mail and Both collapses to a single mail entry).
     *
     * @return array<int, string>
     */
    public function channels(bool $hasPhone): array
    {
        $channels = match ($this) {
            self::Mail => ['mail'],
            self::Sms => [SmsChannel::class],
            self::Both => ['mail', SmsChannel::class],
        };

        if (! $hasPhone) {
            $channels = array_map(
                fn (string $channel): string => $channel === SmsChannel::class ? 'mail' : $channel,
                $channels,
            );
        }

        return array_values(array_unique($channels));
    }
}
