<?php

namespace App\Http\Requests\Settings;

use App\Enums\NotificationChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notification_channel' => ['required', Rule::enum(NotificationChannel::class)],
            'phone' => [
                Rule::requiredIf(fn (): bool => $this->input('notification_channel') !== NotificationChannel::Mail->value),
                'nullable',
                'string',
                'max:25',
                'regex:/^\+?[0-9\s().-]{7,25}$/',
            ],
        ];
    }
}
