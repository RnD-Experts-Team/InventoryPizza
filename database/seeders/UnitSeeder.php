<?php

namespace Database\Seeders;

use App\Models\Inventory\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = ['Carton', 'Box', 'Pack', 'Piece', 'Kilogram', 'Gram', 'Liter', 'Bottle', 'Bag', 'Can'];

        foreach ($units as $name) {
            Unit::updateOrCreate(['name' => $name]);
        }
    }
}
