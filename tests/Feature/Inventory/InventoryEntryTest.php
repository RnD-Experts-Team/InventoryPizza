<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Entry;
use App\Models\Inventory\EntryItem;
use App\Models\Inventory\InventoryLink;
use App\Models\Inventory\Item;
use App\Models\Inventory\Unit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryEntryTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(): Entry
    {
        $store = Store::factory()->create();
        $user  = User::factory()->create(['role' => 'inventory_specialist']);
        $u1    = Unit::factory()->create();
        $u2    = Unit::factory()->create();
        $item  = Item::factory()->create(['unit_1_id' => $u1->id, 'unit_2_id' => $u2->id, 'unit_2_per_unit_1' => 6]);

        $link = InventoryLink::create([
            'token'      => 'entry-test-token',
            'user_name'  => 'Test',
            'store_id'   => $store->id,
            'date'       => now()->toDateString(),
            'type'       => 'daily',
            'status'     => 'submitted',
            'created_by' => $user->id,
        ]);

        $entry = Entry::create([
            'link_id'      => $link->id,
            'submitted_by' => 'Test',
            'store_id'     => $store->id,
            'date'         => now()->toDateString(),
            'type'         => 'daily',
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        EntryItem::create([
            'entry_id'       => $entry->id,
            'item_id'        => $item->id,
            'count_unit_1'   => 1,
            'count_unit_2'   => 0,
            'count_unit_3'   => 0,
            'total_in_unit_1' => 1,
            'is_edited'      => false,
        ]);

        return $entry;
    }

    public function test_both_roles_can_list_entries(): void
    {
        $this->makeEntry();

        foreach (['inventory_specialist', 'store_manager'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/inventory/entries')
                ->assertOk()
                ->assertJsonCount(1, 'data');
        }
    }

    public function test_both_roles_can_view_entry_detail(): void
    {
        $entry = $this->makeEntry();

        foreach (['inventory_specialist', 'store_manager'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson("/api/inventory/entries/{$entry->id}")
                ->assertOk()
                ->assertJsonStructure(['data' => ['items']]);
        }
    }

    public function test_entry_detail_includes_items_with_edits_array(): void
    {
        $entry = $this->makeEntry();

        $this->actingAs(User::factory()->create(['role' => 'inventory_specialist']))
            ->getJson("/api/inventory/entries/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.edits', []);
    }

    public function test_edited_items_count_is_correct(): void
    {
        $entry = $this->makeEntry();
        $entry->items()->first()->update(['is_edited' => true]);

        $this->actingAs(User::factory()->create(['role' => 'inventory_specialist']))
            ->getJson('/api/inventory/entries')
            ->assertOk()
            ->assertJsonPath('data.0.edited_items_count', 1);
    }
}
