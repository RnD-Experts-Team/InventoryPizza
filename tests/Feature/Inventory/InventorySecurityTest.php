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

class InventorySecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntryItem(): EntryItem
    {
        $store = Store::factory()->create();
        $user  = User::factory()->create(['role' => 'store_manager']);
        $u1    = Unit::factory()->create();
        $u2    = Unit::factory()->create();
        $item  = Item::factory()->create(['unit_1_id' => $u1->id, 'unit_2_id' => $u2->id, 'unit_2_per_unit_1' => 6]);

        $link = InventoryLink::create([
            'token'      => 'sec-test-token',
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

        return EntryItem::create([
            'entry_id'       => $entry->id,
            'item_id'        => $item->id,
            'count_unit_1'   => 2,
            'count_unit_2'   => 0,
            'count_unit_3'   => 0,
            'total_in_unit_1' => 2,
            'is_edited'      => false,
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/inventory/units')->assertUnauthorized();
        $this->getJson('/api/inventory/items')->assertUnauthorized();
        $this->getJson('/api/inventory/links')->assertUnauthorized();
        $this->getJson('/api/inventory/entries')->assertUnauthorized();
    }

    public function test_store_manager_cannot_manage_units(): void
    {
        $manager = User::factory()->create(['role' => 'store_manager']);

        $this->actingAs($manager)->getJson('/api/inventory/units')->assertForbidden();
        $this->actingAs($manager)->postJson('/api/inventory/units', ['name' => 'X'])->assertForbidden();
    }

    public function test_store_manager_cannot_manage_items(): void
    {
        $manager = User::factory()->create(['role' => 'store_manager']);

        $this->actingAs($manager)->getJson('/api/inventory/items')->assertForbidden();
        $this->actingAs($manager)->postJson('/api/inventory/items', [])->assertForbidden();
    }

    public function test_inventory_specialist_cannot_edit_entry_item_counts(): void
    {
        $entryItem  = $this->makeEntryItem();
        $specialist = User::factory()->create(['role' => 'inventory_specialist']);

        $this->actingAs($specialist)
            ->patchJson("/api/inventory/entry-items/{$entryItem->id}", [
                'count_unit_1' => 1,
                'count_unit_2' => 0,
                'reason'       => 'Trying to edit without permission',
            ])
            ->assertForbidden();
    }

    public function test_public_submit_on_already_submitted_link_returns_410(): void
    {
        $this->getJson('/api/public/inventory/nonexistent-token')->assertNotFound();
    }
}
