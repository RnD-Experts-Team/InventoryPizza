<?php

declare(strict_types=1);

namespace App\Reports\Datasets;

use App\Reports\Contract\Column;
use App\Reports\Contract\ReportDataset;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One row per counted item, by the store and the date it was counted for.
 * `quantity` is the item's total in its first unit (what the count screens
 * and GET /inventory/stores/{store}/counts call total_in_unit_1), so it adds
 * up across entries and unit conversions are already applied.
 *
 * Like that endpoint, only the latest entry of a store and date is read; an
 * older duplicate would double every quantity (the entries dataset shows them).
 */
class CountsDataset extends ReportDataset
{
    public const KEY = 'inventory.counts';

    public function label(): string
    {
        return 'Inventory counts';
    }

    public function grain(): array
    {
        return ['store', 'date'];
    }

    public function heavy(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            new Column('store', 'Store', 'string', 'stores.store_number', groupable: true),
            new Column('date', 'Count date', 'date', $this->dateColumn(), groupable: true),
            new Column('item', 'Item', 'string', 'inventory_items.name_en', groupable: true),
            new Column('unit', 'Unit', 'string', 'inventory_units.name', groupable: true),
            new Column('quantity', 'Quantity (first unit)', 'number', 'inventory_entry_items.total_in_unit_1', filterable: false, measure: true, aggregations: ['sum', 'avg', 'min', 'max']),
            new Column('count', 'Counts', 'integer', 'inventory_entry_items.id', filterable: false, measure: true, aggregations: ['count']),
        ];
    }

    public function baseQuery(): Builder
    {
        $latest = DB::table('inventory_entries')
            ->selectRaw('MAX(id) as entry_id')
            ->groupBy('store_id', 'date');

        return DB::table('inventory_entry_items')
            ->join('inventory_entries', 'inventory_entries.id', '=', 'inventory_entry_items.entry_id')
            ->joinSub($latest, 'latest_entries', fn ($join) => $join->on('latest_entries.entry_id', '=', 'inventory_entries.id'))
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_entry_items.item_id')
            ->join('inventory_units', 'inventory_units.id', '=', 'inventory_items.unit_1_id')
            ->join('stores', 'stores.id', '=', 'inventory_entries.store_id');
    }

    public function storeColumn(): string
    {
        return 'inventory_entries.store_id';
    }

    /** A DATE column: the day the count is for, already local. */
    public function dateColumn(): string
    {
        return 'inventory_entries.date';
    }
}
