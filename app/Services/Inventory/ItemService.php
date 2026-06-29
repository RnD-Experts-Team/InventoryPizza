<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ItemService
{
    public function getAll(int $perPage = 50): LengthAwarePaginator
    {
        return Item::with(['unit1', 'unit2', 'unit3', 'stores'])->paginate($perPage);
    }

    public function create(array $data, ?UploadedFile $image, ?User $creator = null): Item
    {
        $item = Item::create(array_merge($data, ['image' => null, 'created_by' => $creator?->id]));

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

    public function delete(Item $item): void
    {
        $imagePath = $item->image;

        DB::transaction(function () use ($item) {
            $item->delete();
        });

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
    }
}
