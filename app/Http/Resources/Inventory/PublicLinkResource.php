<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_name' => $this->user_name,
            'store'     => $this->whenLoaded('store', fn () => [
                'store_number' => $this->store->store_number,
                'name'         => $this->store->name,
            ]),
            'date'      => $this->date?->toDateString(),
            'type'      => $this->type,
            'items'     => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'                => $item->id,
                'ultimatrix_id'     => $item->ultimatrix_id,
                'name_en'           => $item->name_en,
                'name_ar'           => $item->name_ar,
                'name_es'           => $item->name_es,
                'details_en'        => $item->details_en,
                'details_ar'        => $item->details_ar,
                'details_es'        => $item->details_es,
                'image'             => $item->image ? asset('storage/'.$item->image) : null,
                'unit_1'            => ['name' => $item->unit1?->name],
                'unit_2'            => ['name' => $item->unit2?->name],
                'unit_2_per_unit_1' => $item->unit_2_per_unit_1,
                'unit_3'            => $item->unit3 ? ['name' => $item->unit3->name] : null,
                'unit_3_per_unit_2' => $item->unit_3_per_unit_2,
            ])),
        ];
    }
}
