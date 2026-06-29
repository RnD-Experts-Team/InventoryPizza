<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\EntryItem;
use App\Models\Inventory\EntryItemEdit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntryItemEditFactory extends Factory
{
    protected $model = EntryItemEdit::class;

    public function definition(): array
    {
        $prevU1 = fake()->numberBetween(0, 50);
        $newU1  = fake()->numberBetween(0, 50);
        $u2PerU1 = 6;

        return [
            'entry_item_id'     => EntryItem::factory(),
            'prev_count_unit_1' => $prevU1,
            'prev_count_unit_2' => 0,
            'prev_count_unit_3' => 0,
            'prev_total'        => $prevU1,
            'new_count_unit_1'  => $newU1,
            'new_count_unit_2'  => 0,
            'new_count_unit_3'  => 0,
            'new_total'         => $newU1,
            'reason'            => fake()->sentence(),
            'edited_by'         => User::factory(),
            'edited_at'         => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
