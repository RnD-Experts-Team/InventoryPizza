<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $table = 'inventory_units';

    protected $fillable = ['name', 'created_by'];

    public function itemsAsUnit1(): HasMany
    {
        return $this->hasMany(Item::class, 'unit_1_id');
    }

    public function itemsAsUnit2(): HasMany
    {
        return $this->hasMany(Item::class, 'unit_2_id');
    }

    public function itemsAsUnit3(): HasMany
    {
        return $this->hasMany(Item::class, 'unit_3_id');
    }
}
