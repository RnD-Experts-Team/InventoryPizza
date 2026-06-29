<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Users are normally provisioned by the Auth Service (IDs set explicitly).
        $users = [
            ['id' => 1, 'name' => 'Test User',          'email' => 'test@example.com',        'role' => 'inventory_specialist'],
            ['id' => 2, 'name' => 'Store Manager',      'email' => 'manager@example.com',     'role' => 'store_manager'],
            ['id' => 3, 'name' => 'Inventory Spec',     'email' => 'specialist@example.com',  'role' => 'inventory_specialist'],
        ];

        foreach ($users as $attrs) {
            User::updateOrCreate(
                ['id' => $attrs['id']],
                array_merge($attrs, [
                    'email_verified_at' => now(),
                    'password'          => Hash::make('password'),
                ]),
            );
        }

        // A few extra specialists with auto-assigned IDs.
        User::factory(5)->create(['role' => 'inventory_specialist']);

        // The store manager manages two stores (requires StoreSeeder to have run first).
        $manager = User::find(2);
        $managedStoreIds = Store::whereIn('id', ['STORE-0001-DT', 'STORE-0002-WS'])->pluck('id');
        if ($manager && $managedStoreIds->isNotEmpty()) {
            $manager->managedStores()->sync($managedStoreIds);
        }
    }
}
