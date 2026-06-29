<?php

namespace App\Models\Inventory;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    use HasFactory;

    protected $table = 'inventory_entries';

    protected $fillable = [
        'link_id', 'submitted_by', 'store_id', 'date', 'type', 'status', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'date'         => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(InventoryLink::class, 'link_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EntryItem::class, 'entry_id');
    }

    public function getReferenceAttribute(): string
    {
        return 'ENT-'.str_pad($this->id, 3, '0', STR_PAD_LEFT);
    }
}
