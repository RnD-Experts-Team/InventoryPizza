<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\InventoryLink;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InventoryLinkFactory extends Factory
{
    protected $model = InventoryLink::class;

    public function definition(): array
    {
        return [
            'token'      => Str::random(64),
            'user_name'  => fake()->name(),
            'store_id'   => Store::factory(),
            'date'       => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'type'       => fake()->randomElement(['daily', 'weekly', 'period']),
            'status'     => 'active',
            'created_by' => User::factory(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => 'submitted']);
    }
}
