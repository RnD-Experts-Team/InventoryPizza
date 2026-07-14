<?php

namespace App\Services\Inventory;

use App\Models\Inventory\EntryItem;
use App\Models\Inventory\Item;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItemService
{
    public function getAll(int $perPage = 50, ?bool $active = null): LengthAwarePaginator
    {
        $query = Item::with(['unit1', 'unit2', 'unit3', 'stores']);

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, ?UploadedFile $image, ?User $creator = null): Item
    {
        $data = $this->normalizeUnits($data);
        $item = Item::create(array_merge(
            ['is_active' => true],                                  // new items are active by default
            $data,
            ['image' => null, 'created_by' => $creator?->id],
        ));

        if ($image) {
            $path = $image->store("inventory/items/{$item->id}", 'public');
            $item->update(['image' => $path]);
        }

        if (! $data['all_stores'] && ! empty($data['store_ids'])) {
            $item->stores()->sync($data['store_ids']);
        }

        return $item->load(['unit1', 'unit2', 'unit3', 'stores']);
    }

    public function update(Item $item, array $data, ?UploadedFile $image): Item
    {
        $data = $this->normalizeUnits($data);

        if ($image) {
            if ($item->image) {
                Storage::disk('public')->delete($item->image);
            }
            $data['image'] = $image->store("inventory/items/{$item->id}", 'public');
        }

        $item->update($data);

        if (! $data['all_stores'] && isset($data['store_ids'])) {
            $item->stores()->sync($data['store_ids']);
        } elseif ($data['all_stores']) {
            $item->stores()->detach();
        }

        return $item->load(['unit1', 'unit2', 'unit3', 'stores']);
    }

    /**
     * Delete an item. Refuses (with a 422 validation message) if the item is still
     * referenced anywhere that would break history: past inventory entry rows, or
     * links that were generated with this item. The store-assignment pivot cascades
     * automatically at the DB level, so we don't count it here.
     */
    public function delete(Item $item): void
    {
        $entryItemCount = EntryItem::where('item_id', $item->id)->count();
        $linkItemCount  = DB::table('inventory_link_item')->where('item_id', $item->id)->count();

        if ($entryItemCount > 0 || $linkItemCount > 0) {
            throw ValidationException::withMessages([
                'item' => "Cannot delete this item: it is used in {$entryItemCount} inventory count(s) and {$linkItemCount} link(s). Historical data must be preserved.",
            ]);
        }

        $imagePath = $item->image;

        DB::transaction(function () use ($item) {
            $item->delete();
        });

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    /** Activate or deactivate an item (soft on/off — the row is never removed). */
    public function setActive(Item $item, bool $active): Item
    {
        $item->update(['is_active' => $active]);

        return $item->load(['unit1', 'unit2', 'unit3', 'stores']);
    }

    /**
     * When there's no second unit, the dependent fields (ratio + third unit)
     * make no sense — null them so we never store orphaned values.
     */
    private function normalizeUnits(array $data): array
    {
        if (empty($data['unit_2_id'])) {
            $data['unit_2_id']         = null;
            $data['unit_2_per_unit_1'] = null;
            $data['unit_3_id']         = null;
            $data['unit_3_per_unit_2'] = null;
        }

        return $data;
    }
}
