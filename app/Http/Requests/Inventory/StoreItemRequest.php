<?php

namespace App\Http\Requests\Inventory;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ultimatrix_id'     => ['required', 'string', 'unique:inventory_items,ultimatrix_id'],
            'name_en'           => ['required', 'string', 'max:255'],
            'name_ar'           => ['required', 'string', 'max:255'],
            'name_es'           => ['required', 'string', 'max:255'],
            'details_en'        => ['nullable', 'string'],
            'details_ar'        => ['nullable', 'string'],
            'details_es'        => ['nullable', 'string'],
            'image'             => ['nullable', 'file', 'mimes:jpg,jpeg,png'],
            'unit_1_id'         => ['required', 'exists:inventory_units,id'],
            'unit_2_id'         => ['required', 'exists:inventory_units,id', 'different:unit_1_id'],
            'unit_2_per_unit_1' => ['required', 'numeric', 'min:0.0001'],
            'unit_3_id'         => ['nullable', 'exists:inventory_units,id'],
            'unit_3_per_unit_2' => ['required_with:unit_3_id', 'nullable', 'numeric', 'min:0.0001'],
            'types'             => ['required', 'array'],
            'types.*'           => ['in:daily,weekly,period'],
            'all_stores'        => ['required', 'boolean'],
            // Frontend sends store_numbers, not internal ids.
            'store_ids'         => ['required_if:all_stores,false', 'nullable', 'array'],
            'store_ids.*'       => ['exists:stores,store_number'],
        ];
    }

    /**
     * Convert the store_numbers the frontend sent into internal integer store ids.
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        if (! empty($data['store_ids'])) {
            $data['store_ids'] = Store::whereIn('store_number', $data['store_ids'])->pluck('id')->all();
        }

        return $data;
    }
}
