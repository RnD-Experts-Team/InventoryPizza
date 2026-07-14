<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public counting form. Item names/details are returned in the single
 * language the link was generated with (link->lang), so the employee only
 * ever sees the intended language.
 */
class PublicLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang         = in_array($this->lang, ['en', 'ar', 'es'], true) ? $this->lang : 'en';
        $nameField    = "name_{$lang}";
        $detailsField = "details_{$lang}";

        return [
            'user_name' => $this->user_name,
            'lang'      => $lang,
            'store'     => $this->whenLoaded('store', fn () => [
                'store_number' => $this->store->store_number,
                'name'         => $this->store->name,
            ]),
            'date'      => $this->date?->toDateString(),
            'type'      => $this->type,
            'items'     => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'                => $item->id,
                'ultimatrix_id'     => $item->ultimatrix_id,
                'name'              => $item->{$nameField},
                'details'           => $item->{$detailsField},
                'image'             => $item->image ? asset('storage/'.$item->image) : null,
                'unit_1'            => ['name' => $item->unit1?->name],
                'unit_2'            => $item->unit2 ? ['name' => $item->unit2->name] : null,
                'unit_2_per_unit_1' => $item->unit_2_per_unit_1,
                'unit_3'            => $item->unit3 ? ['name' => $item->unit3->name] : null,
                'unit_3_per_unit_2' => $item->unit_3_per_unit_2,
            ])),
        ];
    }
}
