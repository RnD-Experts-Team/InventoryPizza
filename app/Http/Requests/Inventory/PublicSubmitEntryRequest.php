<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\InventoryLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublicSubmitEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'               => ['required', 'array', 'min:1'],
            'items.*.item_id'     => ['required', 'exists:inventory_items,id'],
            'items.*.count_unit_1' => ['required', 'numeric', 'min:0'],
            'items.*.count_unit_2' => ['required', 'numeric', 'min:0'],
            'items.*.count_unit_3' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $token = $this->route('token');
            $link = InventoryLink::where('token', $token)->with('items')->first();

            if (! $link) {
                return;
            }

            $linkItemIds = $link->items->pluck('id')->toArray();
            $submittedIds = collect($this->input('items', []))->pluck('item_id')->toArray();

            foreach ($submittedIds as $itemId) {
                if (! in_array($itemId, $linkItemIds)) {
                    $validator->errors()->add('items', "Item ID {$itemId} does not belong to this link.");
                }
            }
        });
    }
}
