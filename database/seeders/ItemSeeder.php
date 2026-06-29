<?php

namespace Database\Seeders;

use App\Models\Inventory\Item;
use App\Models\Inventory\Unit;
use App\Models\Store;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $carton = Unit::where('name', 'Carton')->first();
        $box    = Unit::where('name', 'Box')->first();
        $piece  = Unit::where('name', 'Piece')->first();
        $kg     = Unit::where('name', 'Kilogram')->first();
        $gram   = Unit::where('name', 'Gram')->first();

        $stores = Store::pluck('id')->all();

        $items = [
            [
                'ultimatrix_id' => 'ITM-1001', 'name_en' => 'Cooking Oil', 'name_ar' => 'زيت طهي', 'name_es' => 'Aceite de Cocina',
                'unit_1_id' => $carton->id, 'unit_2_id' => $box->id, 'unit_2_per_unit_1' => 6,
                'unit_3_id' => $piece->id, 'unit_3_per_unit_2' => 12, 'types' => ['daily', 'weekly'],
            ],
            [
                'ultimatrix_id' => 'ITM-1002', 'name_en' => 'Flour', 'name_ar' => 'دقيق', 'name_es' => 'Harina',
                'unit_1_id' => $kg->id, 'unit_2_id' => $gram->id, 'unit_2_per_unit_1' => 1000,
                'unit_3_id' => null, 'unit_3_per_unit_2' => null, 'types' => ['weekly'],
            ],
            [
                'ultimatrix_id' => 'ITM-1003', 'name_en' => 'Sugar', 'name_ar' => 'سكر', 'name_es' => 'Azúcar',
                'unit_1_id' => $carton->id, 'unit_2_id' => $box->id, 'unit_2_per_unit_1' => 8,
                'unit_3_id' => null, 'unit_3_per_unit_2' => null, 'types' => ['daily'],
            ],
            [
                'ultimatrix_id' => 'ITM-1004', 'name_en' => 'Rice', 'name_ar' => 'أرز', 'name_es' => 'Arroz',
                'unit_1_id' => $carton->id, 'unit_2_id' => $box->id, 'unit_2_per_unit_1' => 10,
                'unit_3_id' => $piece->id, 'unit_3_per_unit_2' => 4, 'types' => ['period'],
            ],
            [
                'ultimatrix_id' => 'ITM-1005', 'name_en' => 'Salt', 'name_ar' => 'ملح', 'name_es' => 'Sal',
                'unit_1_id' => $box->id, 'unit_2_id' => $piece->id, 'unit_2_per_unit_1' => 24,
                'unit_3_id' => null, 'unit_3_per_unit_2' => null, 'types' => ['daily', 'weekly', 'period'],
            ],
        ];

        foreach ($items as $attrs) {
            $item = Item::updateOrCreate(
                ['ultimatrix_id' => $attrs['ultimatrix_id']],
                $attrs + ['all_stores' => true],
            );
            // all_stores items are available everywhere; still record explicit pivot rows.
            $item->stores()->sync($stores);
        }

        // A couple of store-specific items (all_stores = false).
        Item::factory(3)
            ->state(['all_stores' => false])
            ->create()
            ->each(fn (Item $item) => $item->stores()->sync(
                collect($stores)->random(min(2, count($stores)))->all()
            ));
    }
}
