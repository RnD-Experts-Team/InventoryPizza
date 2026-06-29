<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Entry;
use App\Models\Inventory\EntryItem;
use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntryItemFactory extends Factory
{
    protected $model = EntryItem::class;

    public function definition(): array
    {
        $countU1 = fake()->numberBetween(0, 50);
        $countU2 = fake()->numberBetween(0, 5);
        $u2PerU1 = 6;

        return [
            'entry_id'        => Entry::factory(),
            'item_id'         => Item::factory(),
            'count_unit_1'    => $countU1,
            'count_unit_2'    => $countU2,
            'count_unit_3'    => 0,
            'total_in_unit_1' => round($countU1 + ($countU2 / $u2PerU1), 4),
            'is_edited'       => false,
        ];
    }
}
