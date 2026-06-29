<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryItemEdit extends Model
{
    use HasFactory;

    protected $table = 'inventory_entry_item_edits';

    public $timestamps = false;

    protected $fillable = [
        'entry_item_id',
        'prev_count_unit_1', 'prev_count_unit_2', 'prev_count_unit_3', 'prev_total',
        'new_count_unit_1', 'new_count_unit_2', 'new_count_unit_3', 'new_total',
        'reason', 'edited_by', 'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'prev_count_unit_1' => 'decimal:4',
            'prev_count_unit_2' => 'decimal:4',
            'prev_count_unit_3' => 'decimal:4',
            'prev_total'        => 'decimal:4',
            'new_count_unit_1'  => 'decimal:4',
            'new_count_unit_2'  => 'decimal:4',
            'new_count_unit_3'  => 'decimal:4',
            'new_total'         => 'decimal:4',
            'edited_at'         => 'datetime',
        ];
    }

    public function entryItem(): BelongsTo
    {
        return $this->belongsTo(EntryItem::class, 'entry_item_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
