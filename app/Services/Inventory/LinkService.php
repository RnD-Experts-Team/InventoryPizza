<?php

namespace App\Services\Inventory;

use App\Models\Employee;
use App\Models\Inventory\InventoryLink;
use App\Models\Inventory\Item;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LinkService
{
    /**
     * Create one link per selected employee. Each employee gets their own token.
     *
     * Items are always auto-resolved from the store + type — the caller
     * does not send item_ids.
     *
     * @return Collection<int, InventoryLink>
     */
    public function generate(array $data, User $creator): Collection
    {
        $itemIds = $this->resolveItemsForStore($data['type'], $data['store_id']);

        if (empty($itemIds)) {
            throw ValidationException::withMessages([
                'type' => ["No '{$data['type']}' items are configured for this store."],
            ]);
        }

        $employees = Employee::query()->whereIn('id', $data['employee_ids'])->get();

        return DB::transaction(fn () => $employees->map(function (Employee $employee) use ($data, $creator, $itemIds) {
            $link = InventoryLink::create([
                'token'       => $this->uniqueToken(),
                'user_name'   => trim("{$employee->first_name} {$employee->last_name}"),
                'employee_id' => $employee->id,
                'store_id'    => $data['store_id'],
                'date'        => $data['date'],
                'type'        => $data['type'],
                'lang'        => $data['lang'],
                'status'      => 'active',
                'created_by'  => $creator->id,
            ]);

            $link->items()->attach($itemIds);

            return $link->load(['items', 'store', 'creator', 'employee']);
        })->values());
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (InventoryLink::where('token', $token)->exists());

        return $token;
    }

    /**
     * Auto-select items of the given type available in the store:
     * either flagged for all stores, or explicitly assigned to this store.
     *
     * @return array<int, int>
     */
    private function resolveItemsForStore(string $type, string $storeId): array
    {
        return Item::query()
            ->where('is_active', true)
            ->whereJsonContains('types', $type)
            ->where(function ($q) use ($storeId) {
                $q->where('all_stores', true)
                    ->orWhereHas('stores', fn ($s) => $s->where('stores.id', $storeId));
            })
            ->pluck('id')
            ->all();
    }

    public function markSubmitted(InventoryLink $link): void
    {
        $link->status = 'submitted';
        $link->save();
    }
}
