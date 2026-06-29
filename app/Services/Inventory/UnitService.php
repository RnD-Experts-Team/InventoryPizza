<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Unit;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UnitService
{
    public function getAll(int $perPage = 50): LengthAwarePaginator
    {
        return Unit::withCount([
            'itemsAsUnit1 as unit_1_items_count',
            'itemsAsUnit2 as unit_2_items_count',
            'itemsAsUnit3 as unit_3_items_count',
        ])->paginate($perPage)->through(function (Unit $unit) {
            $unit->items_count = $unit->unit_1_items_count
                + $unit->unit_2_items_count
                + $unit->unit_3_items_count;
            return $unit;
        });
    }

    public function create(array $data, ?User $creator = null): Unit
    {
        return Unit::create(array_merge($data, ['created_by' => $creator?->id]));
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($data);

        return $unit;
    }

    public function delete(Unit $unit): void
    {
        $count = $unit->itemsAsUnit1()->count()
            + $unit->itemsAsUnit2()->count()
            + $unit->itemsAsUnit3()->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                'unit' => "Cannot delete unit: it is referenced by {$count} item(s).",
            ]);
        }

        $unit->delete();
    }
}
