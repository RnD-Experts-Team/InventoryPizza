<?php

namespace App\Services\Inventory;

use App\Exceptions\LinkAlreadySubmittedException;
use App\Models\Inventory\Entry;
use App\Models\Inventory\EntryItem;
use App\Models\Inventory\InventoryLink;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EntryService
{
    public function __construct(
        private readonly UnitCalculatorService $calculator,
        private readonly LinkService $linkService,
    ) {}

    public function getAll(?string $storeId = null, int $perPage = 50): LengthAwarePaginator
    {
        $query = Entry::with('store')
            ->withCount(['items', 'items as edited_items_count' => fn ($q) => $q->where('is_edited', true)])
            ->latest();

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query->paginate($perPage);
    }

    public function getOne(Entry $entry, bool $withEditHistory = false): Entry
    {
        $relations = [
            'store',
            'items.item.unit1',
            'items.item.unit2',
            'items.item.unit3',
        ];

        if ($withEditHistory) {
            $relations[] = 'items.edits.editor';
        }

        $entry->load($relations)->loadCount([
            'items',
            'items as edited_items_count' => fn ($q) => $q->where('is_edited', true),
        ]);

        return $entry;
    }

    public function createFromPublicSubmission(InventoryLink $link, array $itemCounts): Entry
    {
        return DB::transaction(function () use ($link, $itemCounts) {
            // Re-fetch with a row lock — prevents duplicate submissions under concurrent requests
            $link = InventoryLink::where('id', $link->id)->lockForUpdate()->firstOrFail();

            if ($link->isSubmitted()) {
                throw new LinkAlreadySubmittedException();
            }

            $entry = Entry::create([
                'link_id'      => $link->id,
                'submitted_by' => $link->user_name,
                'store_id'     => $link->store_id,
                'date'         => $link->date,
                'type'         => $link->type,
                'status'       => 'submitted',
                'submitted_at' => now(),
            ]);

            foreach ($itemCounts as $row) {
                $item = \App\Models\Inventory\Item::find($row['item_id']);
                $countU3 = $row['count_unit_3'] ?? 0;
                $u3PerU2 = (float) ($item->unit_3_per_unit_2 ?? 0);

                $total = $this->calculator->calculate(
                    (float) $row['count_unit_1'],
                    (float) $row['count_unit_2'],
                    (float) $countU3,
                    (float) $item->unit_2_per_unit_1,
                    $u3PerU2,
                );

                EntryItem::create([
                    'entry_id'       => $entry->id,
                    'item_id'        => $item->id,
                    'count_unit_1'   => $row['count_unit_1'],
                    'count_unit_2'   => $row['count_unit_2'],
                    'count_unit_3'   => $countU3,
                    'total_in_unit_1' => $total,
                    'is_edited'      => false,
                ]);
            }

            $this->linkService->markSubmitted($link);

            return $entry->load(['store', 'items.item.unit1', 'items.item.unit2', 'items.item.unit3']);
        });
    }

    public function editEntryItem(
        EntryItem $entryItem,
        array $newCounts,
        string $reason,
        User $editor,
    ): EntryItem {
        return DB::transaction(function () use ($entryItem, $newCounts, $reason, $editor) {
            $item = $entryItem->item;
            $countU3 = $newCounts['count_unit_3'] ?? 0;
            $u3PerU2 = (float) ($item->unit_3_per_unit_2 ?? 0);

            $newTotal = $this->calculator->calculate(
                (float) $newCounts['count_unit_1'],
                (float) $newCounts['count_unit_2'],
                (float) $countU3,
                (float) $item->unit_2_per_unit_1,
                $u3PerU2,
            );

            \App\Models\Inventory\EntryItemEdit::create([
                'entry_item_id'     => $entryItem->id,
                'prev_count_unit_1' => $entryItem->count_unit_1,
                'prev_count_unit_2' => $entryItem->count_unit_2,
                'prev_count_unit_3' => $entryItem->count_unit_3,
                'prev_total'        => $entryItem->total_in_unit_1,
                'new_count_unit_1'  => $newCounts['count_unit_1'],
                'new_count_unit_2'  => $newCounts['count_unit_2'],
                'new_count_unit_3'  => $countU3,
                'new_total'         => $newTotal,
                'reason'            => $reason,
                'edited_by'         => $editor->id,
                'edited_at'         => now(),
            ]);

            $entryItem->update([
                'count_unit_1'   => $newCounts['count_unit_1'],
                'count_unit_2'   => $newCounts['count_unit_2'],
                'count_unit_3'   => $countU3,
                'total_in_unit_1' => $newTotal,
                'is_edited'      => true,
            ]);

            return $entryItem->load(['item.unit1', 'item.unit2', 'item.unit3', 'edits.editor']);
        });
    }
}
