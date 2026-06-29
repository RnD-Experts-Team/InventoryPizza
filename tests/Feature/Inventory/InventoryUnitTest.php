<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryUnitTest extends TestCase
{
    use RefreshDatabase;

    private function specialist(): User
    {
        return User::factory()->create(['role' => 'inventory_specialist']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'store_manager']);
    }

    public function test_specialist_can_list_units(): void
    {
        Unit::factory()->count(3)->create();

        $this->actingAs($this->specialist())
            ->getJson('/api/inventory/units')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_specialist_can_create_unit(): void
    {
        $this->actingAs($this->specialist())
            ->postJson('/api/inventory/units', ['name' => 'Box'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Box');
    }

    public function test_specialist_can_update_unit(): void
    {
        $unit = Unit::factory()->create(['name' => 'Old']);

        $this->actingAs($this->specialist())
            ->putJson("/api/inventory/units/{$unit->id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New');
    }

    public function test_specialist_can_delete_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->specialist())
            ->deleteJson("/api/inventory/units/{$unit->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('inventory_units', ['id' => $unit->id]);
    }

    public function test_delete_blocked_when_unit_referenced_by_item(): void
    {
        $unit1 = Unit::factory()->create();
        $unit2 = Unit::factory()->create();
        Item::factory()->create(['unit_1_id' => $unit1->id, 'unit_2_id' => $unit2->id]);

        $this->actingAs($this->specialist())
            ->deleteJson("/api/inventory/units/{$unit1->id}")
            ->assertUnprocessable();
    }

    public function test_store_manager_cannot_access_unit_endpoints(): void
    {
        $this->actingAs($this->manager())
            ->getJson('/api/inventory/units')
            ->assertForbidden();
    }
}
