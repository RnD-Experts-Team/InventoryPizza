<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntryItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_entry_items';

    protected $fillable = [
        'entry_id', 'item_id', 'count_unit_1', 'count_unit_2',
        'count_unit_3', 'total_in_unit_1', 'is_edited',
    ];

    protected function casts(): array
    {
        return [
            'is_edited'      => 'boolean',
            'count_unit_1'   => 'decimal:4',
            'count_unit_2'   => 'decimal:4',
            'count_unit_3'   => 'decimal:4',
            'total_in_unit_1' => 'decimal:4',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'entry_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function edits(): HasMany
    {
        return $this->hasMany(EntryItemEdit::class, 'entry_item_id');
    }
}
