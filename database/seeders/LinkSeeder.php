<?php

namespace Database\Seeders;

use App\Models\Inventory\InventoryLink;
use App\Models\Inventory\Item;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class LinkSeeder extends Seeder
{
    public function run(): void
    {
        $stores  = Store::pluck('id')->all();
        $creator = User::first();
        $items   = Item::pluck('id')->all();

        if (empty($stores) || ! $creator || empty($items)) {
            return;
        }

        // Active links (not yet submitted) — one per store.
        foreach ($stores as $storeId) {
            $link = InventoryLink::factory()->create([
                'store_id'   => $storeId,
                'created_by' => $creator->id,
                'status'     => 'active',
            ]);
            $link->items()->sync(collect($items)->random(min(4, count($items)))->all());
        }

        // Submitted links — these get a matching Entry from EntrySeeder.
        InventoryLink::factory(6)
            ->submitted()
            ->create([
                'store_id'   => fn () => collect($stores)->random(),
                'created_by' => $creator->id,
            ])
            ->each(fn (InventoryLink $link) => $link->items()->sync(
                collect($items)->random(min(5, count($items)))->all()
            ));
    }
}
