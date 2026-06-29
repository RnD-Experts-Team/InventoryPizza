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

class InventoryEntryItemEditTest extends TestCase
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
            'token'      => 'edit-test-token',
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

    public function test_store_manager_can_edit_entry_item(): void
    {
        $entryItem = $this->makeEntryItem();
        $manager   = User::factory()->create(['role' => 'store_manager']);

        $this->actingAs($manager)
            ->patchJson("/api/inventory/entry-items/{$entryItem->id}", [
                'count_unit_1' => 3,
                'count_unit_2' => 6,
                'count_unit_3' => 0,
                'reason'       => 'Correcting a mistake',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_edited', true);
    }

    public function test_edit_creates_edit_record_with_snapshot(): void
    {
        $entryItem = $this->makeEntryItem();
        $manager   = User::factory()->create(['role' => 'store_manager']);

        $this->actingAs($manager)
            ->patchJson("/api/inventory/entry-items/{$entryItem->id}", [
                'count_unit_1' => 5,
                'count_unit_2' => 0,
                'count_unit_3' => 0,
                'reason'       => 'Fixing count error here',
            ])
            ->assertOk();

        $this->assertDatabaseHas('inventory_entry_item_edits', [
            'entry_item_id'     => $entryItem->id,
            'prev_count_unit_1' => '2.0000',
            'new_count_unit_1'  => '5.0000',
        ]);
    }

    public function test_is_edited_flips_to_true_after_edit(): void
    {
        $entryItem = $this->makeEntryItem();
        $this->assertFalse($entryItem->is_edited);

        $manager = User::factory()->create(['role' => 'store_manager']);
        $this->actingAs($manager)
            ->patchJson("/api/inventory/entry-items/{$entryItem->id}", [
                'count_unit_1' => 1,
                'count_unit_2' => 0,
                'reason'       => 'Changing to one unit only',
            ]);

        $this->assertTrue($entryItem->fresh()->is_edited);
    }

    public function test_multiple_edits_all_appear_in_edits_array(): void
    {
        $entryItem = $this->makeEntryItem();
        $manager   = User::factory()->create(['role' => 'store_manager']);

        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($manager)
                ->patchJson("/api/inventory/entry-items/{$entryItem->id}", [
                    'count_unit_1' => $i,
                    'count_unit_2' => 0,
                    'reason'       => "Edit number {$i} with reason",
                ]);
        }

        $this->assertDatabaseCount('inventory_entry_item_edits', 3);
    }

    public function test_total_recalculated_correctly_after_edit(): void
    {
        $entryItem = $this->makeEntryItem();
        $manager   = User::factory()->create(['role' => 'store_manager']);

        // 1 unit1 + 6 unit2 (6 unit2/unit1) = 1 + 1 = 2 total
        $this->actingAs($manager)
            ->patchJson("/api/inventory/entry-items/{$entryItem->id}", [
                'count_unit_1' => 1,
                'count_unit_2' => 6,
                'count_unit_3' => 0,
                'reason'       => 'Updating with correct amounts',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_in_unit_1', '2.0000');
    }

    public function test_missing_reason_returns_422(): void
    {
        $entryItem = $this->makeEntryItem();
        $manager   = User::factory()->create(['role' => 'store_manager']);

        $this->actingAs($manager)
            ->patchJson("/api/inventory/entry-items/{$entryItem->id}", [
                'count_unit_1' => 1,
                'count_unit_2' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_inventory_specialist_cannot_edit_entry_item(): void
    {
        $entryItem  = $this->makeEntryItem();
        $specialist = User::factory()->create(['role' => 'inventory_specialist']);

        $this->actingAs($specialist)
            ->patchJson("/api/inventory/entry-items/{$entryItem->id}", [
                'count_unit_1' => 1,
                'count_unit_2' => 0,
                'reason'       => 'Should not be allowed to edit',
            ])
            ->assertForbidden();
    }
}
