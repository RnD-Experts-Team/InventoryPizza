<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        // Stores are normally provisioned by the Auth Service (string IDs).
        $stores = [
            ['id' => 'STORE-0001-DT', 'name' => 'Downtown Branch'],
            ['id' => 'STORE-0002-WS', 'name' => 'Westside Branch'],
            ['id' => 'STORE-0003-AP', 'name' => 'Airport Branch'],
            ['id' => 'STORE-0004-HB', 'name' => 'Harbor Branch'],
        ];

        foreach ($stores as $attrs) {
            Store::updateOrCreate(['id' => $attrs['id']], $attrs + ['is_active' => true]);
        }
    }
}
