<?php

namespace Database\Seeders;

use App\Models\Inventory\Entry;
use App\Models\Inventory\EntryItem;
use App\Models\Inventory\EntryItemEdit;
use App\Models\Inventory\InventoryLink;
use App\Models\User;
use App\Services\Inventory\UnitCalculatorService;
use Illuminate\Database\Seeder;

class EntrySeeder extends Seeder
{
    public function __construct(private readonly UnitCalculatorService $calculator) {}

    public function run(): void
    {
        $editor = User::where('role', 'store_manager')->first() ?? User::first();

        // Build one Entry per submitted link, mirroring EntryService logic.
        $links = InventoryLink::where('status', 'submitted')
            ->with('items')
            ->get();

        foreach ($links as $link) {
            $entry = Entry::create([
                'link_id'      => $link->id,
                'submitted_by' => $link->user_name,
                'store_id'     => $link->store_id,
                'date'         => $link->date,
                'type'         => $link->type,
                'status'       => 'submitted',
                'submitted_at' => now(),
            ]);

            foreach ($link->items as $item) {
                $countU1 = fake()->numberBetween(0, 40);
                $countU2 = fake()->numberBetween(0, (int) max(1, $item->unit_2_per_unit_1) - 1);
                $countU3 = $item->unit_3_id ? fake()->numberBetween(0, 5) : 0;
                $u3PerU2 = (float) ($item->unit_3_per_unit_2 ?? 0);

                $total = $this->calculator->calculate(
                    (float) $countU1,
                    (float) $countU2,
                    (float) $countU3,
                    (float) $item->unit_2_per_unit_1,
                    $u3PerU2,
                );

                $entryItem = EntryItem::create([
                    'entry_id'        => $entry->id,
                    'item_id'         => $item->id,
                    'count_unit_1'    => $countU1,
                    'count_unit_2'    => $countU2,
                    'count_unit_3'    => $countU3,
                    'total_in_unit_1' => $total,
                    'is_edited'       => false,
                ]);

                // Roughly 1 in 4 items gets a manager correction with an audit row.
                if (fake()->boolean(25)) {
                    $this->recordEdit($entryItem, $item, $editor);
                }
            }
        }
    }

    private function recordEdit(EntryItem $entryItem, $item, ?User $editor): void
    {
        $newU1 = fake()->numberBetween(0, 40);
        $newU2 = fake()->numberBetween(0, (int) max(1, $item->unit_2_per_unit_1) - 1);
        $newU3 = $item->unit_3_id ? fake()->numberBetween(0, 5) : 0;
        $u3PerU2 = (float) ($item->unit_3_per_unit_2 ?? 0);

        $newTotal = $this->calculator->calculate(
            (float) $newU1,
            (float) $newU2,
            (float) $newU3,
            (float) $item->unit_2_per_unit_1,
            $u3PerU2,
        );

        EntryItemEdit::create([
            'entry_item_id'     => $entryItem->id,
            'prev_count_unit_1' => $entryItem->count_unit_1,
            'prev_count_unit_2' => $entryItem->count_unit_2,
            'prev_count_unit_3' => $entryItem->count_unit_3,
            'prev_total'        => $entryItem->total_in_unit_1,
            'new_count_unit_1'  => $newU1,
            'new_count_unit_2'  => $newU2,
            'new_count_unit_3'  => $newU3,
            'new_total'         => $newTotal,
            'reason'            => fake()->randomElement([
                'Miscount corrected during review',
                'Damaged stock excluded',
                'Recounted after recount request',
                'Adjusted to match physical count',
            ]),
            'edited_by'         => $editor?->id,
            'edited_at'         => now(),
        ]);

        $entryItem->update([
            'count_unit_1'    => $newU1,
            'count_unit_2'    => $newU2,
            'count_unit_3'    => $newU3,
            'total_in_unit_1' => $newTotal,
            'is_edited'       => true,
        ]);
    }
}
