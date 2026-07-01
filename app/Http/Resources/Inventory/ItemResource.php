<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'ultimatrix_id'     => $this->ultimatrix_id,
            'name_en'           => $this->name_en,
            'name_ar'           => $this->name_ar,
            'name_es'           => $this->name_es,
            'details_en'        => $this->details_en,
            'details_ar'        => $this->details_ar,
            'details_es'        => $this->details_es,
            'image'             => $this->image ? asset('storage/'.$this->image) : null,
            'unit_1'            => new UnitResource($this->whenLoaded('unit1')),
            'unit_2'            => new UnitResource($this->whenLoaded('unit2')),
            'unit_2_per_unit_1' => $this->unit_2_per_unit_1,
            'unit_3'            => $this->unit_3_id ? new UnitResource($this->whenLoaded('unit3')) : null,
            'unit_3_per_unit_2' => $this->unit_3_per_unit_2,
            'types'             => $this->types,
            'all_stores'        => $this->all_stores,
            'stores'            => $this->whenLoaded('stores', fn () => $this->stores->map(fn ($s) => [
                'id'           => $s->id,
                'store_number' => $s->store_number,
                'name'         => $s->name,
            ])),
        ];
    }
}
