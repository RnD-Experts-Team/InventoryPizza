<?php

namespace App\Services\Inventory;

use App\Models\Inventory\EntryItem;
use App\Models\Inventory\Item;
use App\Models\Inventory\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItemService
{
    /** Pass $type ('daily'|'weekly'|'period') to only return items configured for that type. */
    public function getAll(int $perPage = 50, ?bool $active = null, ?string $type = null): LengthAwarePaginator
    {
        $query = Item::with(['unit1', 'unit2', 'unit3', 'stores', 'tags']);

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        if ($type !== null) {
            $query->whereJsonContains('types', $type);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, ?UploadedFile $image, ?User $creator = null): Item
    {
        $tagIds = $this->resolveTags($data['tags'], $creator);
        unset($data['tags']);

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

        $item->tags()->sync($tagIds);

        return $item->load(['unit1', 'unit2', 'unit3', 'stores', 'tags']);
    }

    public function update(Item $item, array $data, ?UploadedFile $image, ?User $editor = null): Item
    {
        $tagIds = $this->resolveTags($data['tags'], $editor);
        unset($data['tags']);

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

        $item->tags()->sync($tagIds);

        return $item->load(['unit1', 'unit2', 'unit3', 'stores', 'tags']);
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

        return $item->load(['unit1', 'unit2', 'unit3', 'stores', 'tags']);
    }

    /**
     * There is no separate "manage tags" screen: the manager just types tag names
     * on the item form. A name matching an existing tag (by name_en) reuses it;
     * anything new creates a tag on the spot.
     *
     * @param  array<int, array{name_en: string, name_ar: string, name_es: string}>  $tags
     * @return array<int, int>
     */
    private function resolveTags(array $tags, ?User $creator): array
    {
        return array_map(
            fn (array $tag) => Tag::firstOrCreate(
                ['name_en' => $tag['name_en']],
                ['name_ar' => $tag['name_ar'], 'name_es' => $tag['name_es'], 'created_by' => $creator?->id],
            )->id,
            $tags,
        );
    }

    /**
     * When there's no second unit, the dependent fields (ratio + third unit)
     * make no sense — null them so we never store orphaned values. Likewise,
     * clearing the third unit on its own must null its ratio too.
     */
    private function normalizeUnits(array $data): array
    {
        if (empty($data['unit_2_id'])) {
            $data['unit_2_id']         = null;
            $data['unit_2_per_unit_1'] = null;
            $data['unit_3_id']         = null;
            $data['unit_3_per_unit_2'] = null;
        } elseif (empty($data['unit_3_id'])) {
            $data['unit_3_id']         = null;
            $data['unit_3_per_unit_2'] = null;
        }

        return $data;
    }
}
