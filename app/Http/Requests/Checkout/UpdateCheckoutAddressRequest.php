<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCheckoutAddressRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'shipping' => ['required', 'array'],
            ...$this->addressRules('shipping', 'required'),
            // Guests have no account email, so the address must carry
            // one for receipts and order updates.
            'shipping.contact_email' => [Rule::requiredIf(fn (): bool => $this->user() === null), 'email', 'max:255'],
            'billing' => ['nullable', 'array'],
            ...$this->addressRules('billing', 'required_with:billing'),
        ];
    }

    /**
     * Address rules for a nested payload; billing fields are only
     * required when a billing address is sent at all.
     *
     * @return array<string, array<int, string>>
     */
    private function addressRules(string $prefix, string $presence): array
    {
        return [
            "{$prefix}.first_name" => [$presence, 'string', 'max:255'],
            "{$prefix}.last_name" => [$presence, 'string', 'max:255'],
            "{$prefix}.line_one" => [$presence, 'string', 'max:255'],
            "{$prefix}.line_two" => ['nullable', 'string', 'max:255'],
            "{$prefix}.city" => [$presence, 'string', 'max:255'],
            "{$prefix}.state" => ['nullable', 'string', 'max:255'],
            "{$prefix}.postcode" => [$presence, 'string', 'max:20'],
            "{$prefix}.country_id" => [$presence, 'integer', 'exists:lunar_countries,id'],
            "{$prefix}.contact_email" => ['nullable', 'email', 'max:255'],
            "{$prefix}.contact_phone" => ['nullable', 'string', 'max:50'],
        ];
    }
}
