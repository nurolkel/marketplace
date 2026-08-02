<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartLineRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'purchasable_id' => ['required', 'integer', 'exists:lunar_product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
