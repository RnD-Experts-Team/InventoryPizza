<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'token'      => $this->token,
            'url'        => url("/api/public/inventory/{$this->token}"),
            'employee'   => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id'        => $this->employee->id,
                'name'      => $this->user_name,
                'store_id'  => $this->employee->store_id,
            ] : null),
            'store'      => $this->whenLoaded('store', fn () => [
                'id'           => $this->store->id,
                'store_number' => $this->store->store_number,
                'name'         => $this->store->name,
            ]),
            'date'       => $this->date?->toDateString(),
            'type'       => $this->type,
            'status'     => $this->status,
            'items_count' => $this->items_count ?? $this->items()->count(),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
