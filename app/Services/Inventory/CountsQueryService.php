<?php

namespace App\Services\Inventory;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Counted quantities for many stores, many dates and selected items — in one query.
 *
 * WHY THIS EXISTS
 * ---------------
 * The entry endpoints cannot answer "what was counted for these stores, on these
 * dates, for these items". `stores/{id}/entries` returns entry headers with no
 * quantities at all (EntryResource's `items_count` is how many items were
 * counted, not how much of them), and `entries/{entry}` is keyed by a single
 * entry id. A consumer wanting one week of three items for one store had to make
 * 8 calls: one to learn the entry ids, then one per entry — then filter three
 * items out of everything the store counted.
 *
 * Every protected call in this service also makes a second, synchronous call to
 * pizzasys to verify the token (AuthTokenStoreScopeMiddleware; its Redis cache is
 * commented out). So 8 calls are 16 network round trips. The Dough & Sauce
 * specialist's screen covers 44 stores: 352 calls, 704 round trips, and 352
 * verifications — to end up with 21 numbers per store.
 *
 * This is one query against one indexed table.
 *
 * DUPLICATE ENTRIES
 * -----------------
 * Nothing in the schema stops two entries existing for the same store and date —
 * `inventory_entries` has no unique key on (store_id, date, type). By default we
 * take the latest and report how many others we skipped, so the choice is visible
 * rather than silent. `all_entries=true` returns everything, for diagnosing it.
 *
 * MISSING IS NOT ZERO
 * -------------------
 * The combinations that produced no row come back in `meta.missing`. The consumer
 * is replacing a spreadsheet whose lookup said `IFERROR(..., 0)` — it could not
 * tell "counted, and there were none" from "never counted", and the plan built on
 * it was short by ~1.7% with nobody informed. Returning the gaps explicitly is the
 * point of this endpoint, not a nicety.
 */
class CountsQueryService
{
    /**
     * Takes an array of store ids rather than one, because the query itself has
     * no reason to care: the restriction to a single store comes from the route
     * shape, which exists so pizzasys can authorize it — not from anything here.
     *
     * @param  array<int, int>     $storeIds    internal integer ids (already resolved)
     * @param  array<int, string>  $itemRefs    ultimatrix_ids; empty means every item
     * @return array<string, mixed>
     */
    public function query(
        array $storeIds,
        string $dateFrom,
        string $dateTo,
        array $itemRefs = [],
        bool $allEntries = false,
    ): array {
        $rows = $this->rows($storeIds, $dateFrom, $dateTo, $itemRefs, $allEntries);

        return [
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo,
            'all_entries'    => $allEntries,
            'data'           => $rows,
            'meta'           => [
                'rows'             => count($rows),
                'stores_with_data' => count(array_unique(array_column($rows, 'store_id'))),
                'missing'          => $itemRefs
                    ? $this->missing($storeIds, $dateFrom, $dateTo, $itemRefs, $rows)
                    : [],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(
        array $storeIds,
        string $dateFrom,
        string $dateTo,
        array $itemRefs,
        bool $allEntries,
    ): array {
        // Selected here, not passed to get(). Builder::get($columns) applies its
        // argument with `??=`, so it is IGNORED once $this->columns is set — and
        // the addSelect('l.dupes') below sets it. Passing the list to get() left
        // the query selecting `l.dupes` alone, and every other field came back
        // undefined. It only surfaced on a store that had rows: with none, the
        // mapping closure never ran.
        $columns = [
            's.store_number as store',
            'e.store_id',
            'e.date',
            'i.ultimatrix_id',
            'i.name_en as item_name',
            'ei.count_unit_1',
            'ei.total_in_unit_1',
            'u.name as unit_1',
            'ei.is_edited',
            'e.id as entry_id',
            'e.submitted_at',
        ];

        $query = DB::table('inventory_entry_items as ei')
            ->select($columns)
            ->join('inventory_entries as e', 'e.id', '=', 'ei.entry_id')
            ->join('inventory_items as i', 'i.id', '=', 'ei.item_id')
            ->join('stores as s', 's.id', '=', 'e.store_id')
            ->join('inventory_units as u', 'u.id', '=', 'i.unit_1_id')
            ->whereIn('e.store_id', $storeIds)
            ->whereBetween('e.date', [$dateFrom, $dateTo]);

        if ($itemRefs) {
            $query->whereIn('i.ultimatrix_id', $itemRefs);
        }

        // One entry per (store, date): the most recently created, with a count of
        // the ones that lost. Skipped when all_entries=true so a duplicate can be
        // looked at rather than guessed about.
        if (! $allEntries) {
            $latest = DB::table('inventory_entries')
                ->selectRaw('store_id, date, MAX(id) as entry_id, COUNT(*) - 1 as dupes')
                ->whereIn('store_id', $storeIds)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->groupBy('store_id', 'date');

            $query->joinSub($latest, 'l', fn ($join) => $join->on('l.entry_id', '=', 'e.id'))
                ->addSelect('l.dupes');
        }

        $rows = $query
            ->orderBy('e.store_id')->orderBy('e.date')->orderBy('i.ultimatrix_id')
            ->get();

        return $rows->map(fn ($r) => [
            'store'             => $r->store,
            'store_id'          => (int) $r->store_id,
            'date'              => Carbon::parse($r->date)->toDateString(),
            'ultimatrix_id'     => $r->ultimatrix_id,
            'item_name'         => $r->item_name,
            'count_unit_1'      => (float) $r->count_unit_1,
            // Read this one. It equals count_unit_1 when the employee counted in
            // the first unit only, and carries UnitCalculatorService's conversion
            // when they used the second or third — so the consumer never has to
            // redo that arithmetic.
            'total_in_unit_1'   => (float) $r->total_in_unit_1,
            'unit_1'            => $r->unit_1,
            'is_edited'         => (bool) $r->is_edited,
            'entry_id'          => (int) $r->entry_id,
            'submitted_at'      => $r->submitted_at ? Carbon::parse($r->submitted_at)->toIso8601String() : null,
            'duplicate_entries' => isset($r->dupes) ? (int) $r->dupes : 0,
        ])->all();
    }

    /**
     * The (store, date, item) combinations that were asked for and produced nothing.
     *
     * Only computable when specific items were requested — without a list there is
     * no expectation to compare against.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function missing(
        array $storeIds,
        string $dateFrom,
        string $dateTo,
        array $itemRefs,
        array $rows,
    ): array {
        $found = [];
        foreach ($rows as $row) {
            $found[$row['store_id'] . '|' . $row['date'] . '|' . $row['ultimatrix_id']] = true;
        }

        $numbers = DB::table('stores')->whereIn('id', $storeIds)->pluck('store_number', 'id');

        $missing = [];
        $cursor  = Carbon::parse($dateFrom);
        $end     = Carbon::parse($dateTo);

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();

            foreach ($storeIds as $storeId) {
                foreach ($itemRefs as $ref) {
                    if (! isset($found[$storeId . '|' . $date . '|' . $ref])) {
                        $missing[] = [
                            'store'         => $numbers[$storeId] ?? null,
                            'store_id'      => (int) $storeId,
                            'date'          => $date,
                            'ultimatrix_id' => $ref,
                        ];
                    }
                }
            }

            $cursor->addDay();
        }

        return $missing;
    }
}
