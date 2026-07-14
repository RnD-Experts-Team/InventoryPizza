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

    /**
     * @param int|null $storeId  internal integer store id (already resolved)
     * @param array    $filters  optional: date_from, date_to, type, submitted_by, edited(bool)
     */
    public function getAll(?int $storeId = null, int $perPage = 50, array $filters = []): LengthAwarePaginator
    {
        $query = Entry::with('store')
            ->withCount(['items', 'items as edited_items_count' => fn ($q) => $q->where('is_edited', true)])
            ->latest();

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['submitted_by'])) {
            $query->where('submitted_by', 'like', '%'.$filters['submitted_by'].'%');
        }
        if (array_key_exists('edited', $filters) && $filters['edited'] !== null) {
            $filters['edited']
                ? $query->whereHas('items', fn ($q) => $q->where('is_edited', true))
                : $query->whereDoesntHave('items', fn ($q) => $q->where('is_edited', true));
        }

        return $query->paginate($perPage);
    }

    /** Load an entry without edit history (basic detail view). */
    public function getOne(Entry $entry): Entry
    {
        return $entry->load([
            'store',
            'items.item.unit1',
            'items.item.unit2',
            'items.item.unit3',
        ])->loadCount([
            'items',
            'items as edited_items_count' => fn ($q) => $q->where('is_edited', true),
        ]);
    }

    /** Load an entry with the full append-only edit history on each item. */
    public function getOneWithHistory(Entry $entry): Entry
    {
        return $entry->load([
            'store',
            'items.item.unit1',
            'items.item.unit2',
            'items.item.unit3',
            'items.edits.editor',
        ])->loadCount([
            'items',
            'items as edited_items_count' => fn ($q) => $q->where('is_edited', true),
        ]);
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
                $countU2 = $row['count_unit_2'] ?? 0;
                $countU3 = $row['count_unit_3'] ?? 0;
                $u2PerU1 = (float) ($item->unit_2_per_unit_1 ?? 0);
                $u3PerU2 = (float) ($item->unit_3_per_unit_2 ?? 0);

                $total = $this->calculator->calculate(
                    (float) $row['count_unit_1'],
                    (float) $countU2,
                    (float) $countU3,
                    $u2PerU1,
                    $u3PerU2,
                );

                EntryItem::create([
                    'entry_id'       => $entry->id,
                    'item_id'        => $item->id,
                    'count_unit_1'   => $row['count_unit_1'],
                    'count_unit_2'   => $countU2,
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
            $countU2 = $newCounts['count_unit_2'] ?? 0;
            $countU3 = $newCounts['count_unit_3'] ?? 0;
            $u2PerU1 = (float) ($item->unit_2_per_unit_1 ?? 0);
            $u3PerU2 = (float) ($item->unit_3_per_unit_2 ?? 0);

            $newTotal = $this->calculator->calculate(
                (float) $newCounts['count_unit_1'],
                (float) $countU2,
                (float) $countU3,
                $u2PerU1,
                $u3PerU2,
            );

            \App\Models\Inventory\EntryItemEdit::create([
                'entry_item_id'     => $entryItem->id,
                'prev_count_unit_1' => $entryItem->count_unit_1,
                'prev_count_unit_2' => $entryItem->count_unit_2,
                'prev_count_unit_3' => $entryItem->count_unit_3,
                'prev_total'        => $entryItem->total_in_unit_1,
                'new_count_unit_1'  => $newCounts['count_unit_1'],
                'new_count_unit_2'  => $countU2,
                'new_count_unit_3'  => $countU3,
                'new_total'         => $newTotal,
                'reason'            => $reason,
                'edited_by'         => $editor->id,
                'edited_at'         => now(),
            ]);

            $entryItem->update([
                'count_unit_1'   => $newCounts['count_unit_1'],
                'count_unit_2'   => $countU2,
                'count_unit_3'   => $countU3,
                'total_in_unit_1' => $newTotal,
                'is_edited'      => true,
            ]);

            return $entryItem->load(['item.unit1', 'item.unit2', 'item.unit3', 'edits.editor']);
        });
    }
}
