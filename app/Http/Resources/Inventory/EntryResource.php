<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'reference'          => $this->reference,
            'submitted_by'       => $this->submitted_by,
            'store'              => $this->whenLoaded('store', fn () => [
                'id'           => $this->store->id,
                'store_number' => $this->store->store_number,
                'name'         => $this->store->name,
            ]),
            'date'               => $this->date?->toDateString(),
            'type'               => $this->type,
            'status'             => $this->status,
            'items_count'        => $this->items_count ?? $this->items()->count(),
            'edited_items_count' => $this->edited_items_count ?? $this->items()->where('is_edited', true)->count(),
            'submitted_at'       => $this->submitted_at?->toIso8601String(),
        ];
    }
}
