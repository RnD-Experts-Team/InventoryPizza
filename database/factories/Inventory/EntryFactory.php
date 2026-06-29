<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Entry;
use App\Models\Inventory\InventoryLink;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        return [
            'link_id'      => InventoryLink::factory(),
            'submitted_by' => fake()->name(),
            'store_id'     => Store::factory(),
            'date'         => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'type'         => fake()->randomElement(['daily', 'weekly', 'period']),
            'status'       => 'submitted',
            'submitted_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status'       => 'pending',
            'submitted_at' => null,
        ]);
    }
}
