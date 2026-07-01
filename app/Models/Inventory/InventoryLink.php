<?php

namespace App\Models\Inventory;

use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryLink extends Model
{
    use HasFactory;

    protected $table = 'inventory_links';

    protected $fillable = [
        'token', 'user_name', 'employee_id', 'store_id', 'date', 'type', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date'     => 'date',
            'status'   => 'string',
            'store_id' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'inventory_link_item', 'link_id', 'item_id');
    }

    public function entry(): HasOne
    {
        return $this->hasOne(Entry::class, 'link_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
