<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $u1 = Unit::factory()->create();
        $u2 = Unit::factory()->create();

        return [
            'ultimatrix_id'     => fake()->unique()->bothify('ITM-####'),
            'name_en'           => fake()->words(2, true),
            'name_ar'           => fake()->words(2, true),
            'name_es'           => fake()->words(2, true),
            'details_en'        => null,
            'details_ar'        => null,
            'details_es'        => null,
            'image'             => null,
            'unit_1_id'         => $u1->id,
            'unit_2_id'         => $u2->id,
            'unit_2_per_unit_1' => 6,
            'unit_3_id'         => null,
            'unit_3_per_unit_2' => null,
            'types'             => ['daily'],
            'all_stores'        => true,
        ];
    }
}
