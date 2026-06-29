<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Entry;
use App\Models\Inventory\InventoryLink;
use App\Models\Inventory\Item;
use App\Models\Inventory\Unit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveLink(): InventoryLink
    {
        $store = Store::factory()->create();
        $user  = User::factory()->create(['role' => 'inventory_specialist']);
        $u1    = Unit::factory()->create(['name' => 'Box']);
        $u2    = Unit::factory()->create(['name' => 'Bag']);
        $item  = Item::factory()->create([
            'unit_1_id'         => $u1->id,
            'unit_2_id'         => $u2->id,
            'unit_2_per_unit_1' => 6,
        ]);

        $link = InventoryLink::create([
            'token'      => 'test-token-abc123',
            'user_name'  => 'Test User',
            'store_id'   => $store->id,
            'date'       => now()->toDateString(),
            'type'       => 'daily',
            'status'     => 'active',
            'created_by' => $user->id,
        ]);

        $link->items()->attach($item->id);

        return $link;
    }

    public function test_public_user_can_load_form_from_valid_token(): void
    {
        $link = $this->makeActiveLink();

        $this->getJson("/api/public/inventory/{$link->token}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['user_name', 'store', 'date', 'type', 'items']]);
    }

    public function test_public_user_can_submit_counts(): void
    {
        $link = $this->makeActiveLink();
        $item = $link->items->first();

        $this->postJson("/api/public/inventory/{$link->token}/submit", [
            'items' => [[
                'item_id'      => $item->id,
                'count_unit_1' => 1,
                'count_unit_2' => 0,
                'count_unit_3' => 0,
            ]],
        ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['reference', 'submitted_at']]);

        $this->assertDatabaseHas('inventory_links', ['token' => $link->token, 'status' => 'submitted']);
        $this->assertDatabaseCount('inventory_entries', 1);
    }

    public function test_second_submission_returns_410(): void
    {
        $link = $this->makeActiveLink();
        $item = $link->items->first();
        $payload = [
            'items' => [['item_id' => $item->id, 'count_unit_1' => 1, 'count_unit_2' => 0]],
        ];

        $this->postJson("/api/public/inventory/{$link->token}/submit", $payload)->assertCreated();
        $this->postJson("/api/public/inventory/{$link->token}/submit", $payload)->assertStatus(410);
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->getJson('/api/public/inventory/bad-token-xyz')->assertNotFound();
    }

    public function test_submitted_item_ids_not_in_link_returns_422(): void
    {
        $link  = $this->makeActiveLink();
        $other = Item::factory()->create();

        $this->postJson("/api/public/inventory/{$link->token}/submit", [
            'items' => [['item_id' => $other->id, 'count_unit_1' => 1, 'count_unit_2' => 0]],
        ])->assertUnprocessable();
    }

    public function test_total_is_calculated_server_side(): void
    {
        $store = Store::factory()->create();
        $user  = User::factory()->create(['role' => 'inventory_specialist']);
        $u1    = Unit::factory()->create(['name' => 'Box']);
        $u2    = Unit::factory()->create(['name' => 'Bag']);
        $u3    = Unit::factory()->create(['name' => 'Piece']);
        $item  = Item::factory()->create([
            'unit_1_id'         => $u1->id,
            'unit_2_id'         => $u2->id,
            'unit_2_per_unit_1' => 6,
            'unit_3_id'         => $u3->id,
            'unit_3_per_unit_2' => 5,
        ]);

        $link = InventoryLink::create([
            'token'      => 'calc-token-xyz',
            'user_name'  => 'Calc User',
            'store_id'   => $store->id,
            'date'       => now()->toDateString(),
            'type'       => 'daily',
            'status'     => 'active',
            'created_by' => $user->id,
        ]);
        $link->items()->attach($item->id);

        // 1 Box + 6 Bags + 5 Pieces (6bag/box, 5pc/bag) = 2.1667 Boxes
        $this->postJson("/api/public/inventory/{$link->token}/submit", [
            'items' => [[
                'item_id'      => $item->id,
                'count_unit_1' => 1,
                'count_unit_2' => 6,
                'count_unit_3' => 5,
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_entry_items', [
            'item_id'        => $item->id,
            'total_in_unit_1' => '2.1667',
        ]);
    }
}
