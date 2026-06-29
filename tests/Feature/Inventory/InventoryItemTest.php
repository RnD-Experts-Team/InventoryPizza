<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Unit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryItemTest extends TestCase
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

    private function basePayload(array $overrides = []): array
    {
        $u1 = Unit::factory()->create();
        $u2 = Unit::factory()->create();

        return array_merge([
            'ultimatrix_id'     => 'ITM-001',
            'name_en'           => 'Box',
            'name_ar'           => 'صندوق',
            'name_es'           => 'Caja',
            'unit_1_id'         => $u1->id,
            'unit_2_id'         => $u2->id,
            'unit_2_per_unit_1' => 6,
            'types'             => ['daily'],
            'all_stores'        => true,
        ], $overrides);
    }

    public function test_specialist_can_create_item_with_two_units(): void
    {
        $this->actingAs($this->specialist())
            ->postJson('/api/inventory/items', $this->basePayload())
            ->assertCreated()
            ->assertJsonPath('data.ultimatrix_id', 'ITM-001');
    }

    public function test_specialist_can_create_item_with_three_units(): void
    {
        $u3 = Unit::factory()->create();
        $payload = $this->basePayload(['unit_3_id' => $u3->id, 'unit_3_per_unit_2' => 5]);

        $this->actingAs($this->specialist())
            ->postJson('/api/inventory/items', $payload)
            ->assertCreated()
            ->assertJsonPath('data.unit_3.id', $u3->id);
    }

    public function test_specialist_can_create_item_with_image(): void
    {
        Storage::fake('public');

        $payload = array_merge($this->basePayload(), [
            'image' => UploadedFile::fake()->image('item.jpg'),
        ]);

        $this->actingAs($this->specialist())
            ->postJson('/api/inventory/items', $payload)
            ->assertCreated()
            ->assertJsonPath('data.image', fn ($v) => str_contains($v, 'inventory/items'));
    }

    public function test_specialist_can_update_item(): void
    {
        Storage::fake('public');
        $item = Item::factory()->create();

        $payload = array_merge($this->basePayload(), [
            'ultimatrix_id' => $item->ultimatrix_id,
            'unit_1_id'     => $item->unit_1_id,
            'unit_2_id'     => $item->unit_2_id,
            'name_en'       => 'Updated Box',
        ]);

        $this->actingAs($this->specialist())
            ->putJson("/api/inventory/items/{$item->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Updated Box');
    }

    public function test_specialist_can_delete_item(): void
    {
        $item = Item::factory()->create();

        $this->actingAs($this->specialist())
            ->deleteJson("/api/inventory/items/{$item->id}")
            ->assertNoContent();
    }

    public function test_store_manager_cannot_access_item_endpoints(): void
    {
        $this->actingAs($this->manager())
            ->getJson('/api/inventory/items')
            ->assertForbidden();
    }

    public function test_duplicate_ultimatrix_id_returns_422(): void
    {
        $existing = Item::factory()->create(['ultimatrix_id' => 'DUPE-001']);
        $payload = $this->basePayload(['ultimatrix_id' => 'DUPE-001']);

        $this->actingAs($this->specialist())
            ->postJson('/api/inventory/items', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ultimatrix_id']);
    }
}
