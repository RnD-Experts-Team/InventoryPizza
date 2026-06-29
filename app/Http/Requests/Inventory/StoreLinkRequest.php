<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'       => ['required', 'exists:stores,id'],
            'date'           => ['required', 'date'],
            'type'           => ['required', 'in:daily,weekly,period'],
            // The employees (counters) the manager picked — one link per employee.
            'employee_ids'   => ['required', 'array', 'min:1'],
            'employee_ids.*' => [
                'integer', 'distinct',
                Rule::exists('employees', 'id')
                    ->where('store_id', $this->input('store_id'))
                    ->where('active', true),
            ],
        ];
    }

}
