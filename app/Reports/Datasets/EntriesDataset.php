<?php

declare(strict_types=1);

namespace App\Reports\Datasets;

use App\Reports\Contract\Column;
use App\Reports\Contract\ReportDataset;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One row per submitted count (inventory entry), by the store and the date it
 * was counted for. Nothing stops two entries for one store and date, so
 * `entries` shows them rather than hiding them.
 */
class EntriesDataset extends ReportDataset
{
    public const KEY = 'inventory.entries';

    public function label(): string
    {
        return 'Inventory entries';
    }

    public function grain(): array
    {
        return ['store', 'date'];
    }

    public function columns(): array
    {
        return [
            new Column('store', 'Store', 'string', 'stores.store_number', groupable: true),
            new Column('date', 'Count date', 'date', $this->dateColumn(), groupable: true),
            new Column('type', 'Count type', 'enum', 'inventory_entries.type', options: [
                ['value' => 'daily', 'label' => 'Daily'],
                ['value' => 'weekly', 'label' => 'Weekly'],
                ['value' => 'period', 'label' => 'Period'],
            ], groupable: true),
            // Only `submitted` is stored today; `pending` was removed.
            new Column('status', 'Status', 'enum', 'inventory_entries.status', options: [
                ['value' => 'submitted', 'label' => 'Submitted'],
            ], groupable: true),
            new Column('entries', 'Entries', 'integer', 'inventory_entries.id', filterable: false, measure: true, aggregations: ['count']),
            // Counted per entry, so entries and items never multiply each other.
            new Column('items_counted', 'Items counted', 'integer', '(SELECT COUNT(*) FROM inventory_entry_items WHERE inventory_entry_items.entry_id = inventory_entries.id)', filterable: false, measure: true, aggregations: ['sum']),
        ];
    }

    public function baseQuery(): Builder
    {
        return DB::table('inventory_entries')
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
