<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    // ID is an integer controlled by the Auth Service (we set it explicitly, not auto-increment)
    public $incrementing = false;
    protected $keyType   = 'int';

    protected $fillable = ['id', 'store_number', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Resolve the internal integer store id from the human-facing store_number
     * the frontend sends. Returns null if no store matches.
     */
    public static function idFromNumber(?string $number): ?int
    {
        if ($number === null || $number === '') {
            return null;
        }

        return static::where('store_number', $number)->value('id');
    }
}

