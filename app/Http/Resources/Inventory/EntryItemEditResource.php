<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryItemEditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'prev_count_unit_1' => $this->prev_count_unit_1,
            'prev_count_unit_2' => $this->prev_count_unit_2,
            'prev_count_unit_3' => $this->prev_count_unit_3,
            'prev_total'        => $this->prev_total,
            'new_count_unit_1'  => $this->new_count_unit_1,
            'new_count_unit_2'  => $this->new_count_unit_2,
            'new_count_unit_3'  => $this->new_count_unit_3,
            'new_total'         => $this->new_total,
            'reason'            => $this->reason,
            'edited_by'         => $this->whenLoaded('editor', fn () => [
                'id'   => $this->editor->id,
                'name' => $this->editor->name,
            ]),
            'edited_at'         => $this->edited_at?->toIso8601String(),
        ];
    }
}
