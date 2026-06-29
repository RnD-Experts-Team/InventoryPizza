<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEntryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'count_unit_1' => ['required', 'numeric', 'min:0'],
            'count_unit_2' => ['required', 'numeric', 'min:0'],
            'count_unit_3' => ['nullable', 'numeric', 'min:0'],
            'reason'       => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
