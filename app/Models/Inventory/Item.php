<?php

namespace App\Models\Inventory;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model
{
    use HasFactory;

    protected $table = 'inventory_items';

    protected $fillable = [
        'ultimatrix_id', 'name_en', 'name_ar', 'name_es',
        'details_en', 'details_ar', 'details_es', 'image',
        'unit_1_id', 'unit_2_id', 'unit_2_per_unit_1',
        'unit_3_id', 'unit_3_per_unit_2', 'types', 'all_stores',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'types'            => 'array',
            'all_stores'       => 'boolean',
            'is_active'        => 'boolean',
            'unit_2_per_unit_1' => 'decimal:4',
            'unit_3_per_unit_2' => 'decimal:4',
        ];
    }

    public function unit1(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_1_id');
    }

    public function unit2(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_2_id');
    }

    public function unit3(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_3_id');
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'inventory_item_store', 'item_id', 'store_id');
    }

    public function links(): BelongsToMany
    {
        return $this->belongsToMany(InventoryLink::class, 'inventory_link_item', 'item_id', 'link_id');
    }
}
