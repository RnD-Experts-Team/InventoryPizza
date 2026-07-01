<?php

namespace App\Http\Requests\Inventory;

use App\Models\Store;
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
        // The frontend sends the store_number as the `store_id` value.
        $realStoreId = Store::idFromNumber((string) $this->input('store_id'));

        return [
            // Value is a store_number, not the internal id.
            'store_id'       => ['required', 'exists:stores,store_number'],
            'date'           => ['required', 'date'],
            'type'           => ['required', 'in:daily,weekly,period'],
            // The employees (counters) the manager picked — one link per employee.
            'employee_ids'   => ['required', 'array', 'min:1'],
            'employee_ids.*' => [
                'integer', 'distinct',
                Rule::exists('employees', 'id')
                    ->where('store_id', $realStoreId)   // resolved integer id
                    ->where('active', true),
            ],
        ];
    }

    /**
     * Hand the controller/service the REAL integer store id,
     * not the store_number the frontend sent.
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $data['store_id'] = Store::idFromNumber((string) $data['store_id']);

        return $data;
    }
}
