<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roles = (array) $request->attributes->get('authz_roles', []);
        $isSpecialist = in_array('inventory_specialist', $roles, true);

        $items = $this->whenLoaded('items', fn () => $this->items->map(fn ($entryItem) => array_merge(
            [
                'id'             => $entryItem->id,
                'item'           => [
                    'id'                => $entryItem->item->id,
                    'ultimatrix_id'     => $entryItem->item->ultimatrix_id,
                    'name_en'           => $entryItem->item->name_en,
                    'name_ar'           => $entryItem->item->name_ar,
                    'name_es'           => $entryItem->item->name_es,
                    'unit_1'            => ['id' => $entryItem->item->unit1->id, 'name' => $entryItem->item->unit1->name],
                    'unit_2'            => ['id' => $entryItem->item->unit2->id, 'name' => $entryItem->item->unit2->name],
                    'unit_2_per_unit_1' => $entryItem->item->unit_2_per_unit_1,
                    'unit_3'            => $entryItem->item->unit3
                        ? ['id' => $entryItem->item->unit3->id, 'name' => $entryItem->item->unit3->name]
                        : null,
                    'unit_3_per_unit_2' => $entryItem->item->unit_3_per_unit_2,
                ],
                'count_unit_1'    => $entryItem->count_unit_1,
                'count_unit_2'    => $entryItem->count_unit_2,
                'count_unit_3'    => $entryItem->count_unit_3,
                'total_in_unit_1' => $entryItem->total_in_unit_1,
            ],
            $isSpecialist ? [
                'is_edited' => $entryItem->is_edited,
                'edits'     => EntryItemEditResource::collection($entryItem->edits),
            ] : [],
        )));

        return array_merge((new EntryResource($this->resource))->toArray($request), [
            'items' => $items,
        ]);
    }
}
