<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Unit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryLinkTest extends TestCase
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

    private function makeLinkPayload(): array
    {
        $store = Store::factory()->create();
        $item = Item::factory()->create();

        return [
            'user_name' => 'John Doe',
            'store_id'  => $store->id,
            'date'      => now()->toDateString(),
            'type'      => 'daily',
            'item_ids'  => [$item->id],
        ];
    }

    public function test_specialist_can_generate_link(): void
    {
        $this->actingAs($this->specialist())
            ->postJson('/api/inventory/links', $this->makeLinkPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['data' => ['token', 'url']]);
    }

    public function test_manager_can_generate_link(): void
    {
        $this->actingAs($this->manager())
            ->postJson('/api/inventory/links', $this->makeLinkPayload())
            ->assertCreated();
    }

    public function test_generated_link_has_unique_token(): void
    {
        $user = $this->specialist();
        $payload = $this->makeLinkPayload();

        $r1 = $this->actingAs($user)->postJson('/api/inventory/links', $payload)->json('data.token');

        $payload2 = $this->makeLinkPayload();
        $r2 = $this->actingAs($user)->postJson('/api/inventory/links', $payload2)->json('data.token');

        $this->assertNotEquals($r1, $r2);
    }

    public function test_link_list_includes_status(): void
    {
        $user = $this->specialist();
        $this->actingAs($user)->postJson('/api/inventory/links', $this->makeLinkPayload());

        $this->actingAs($user)
            ->getJson('/api/inventory/links')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'active');
    }
}
