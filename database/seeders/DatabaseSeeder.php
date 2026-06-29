<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters — each seeder depends on the data created before it.
     */
    public function run(): void
    {
        $this->call([
            StoreSeeder::class,       // stores (needed before users can be assigned managed stores)
            UserSeeder::class,        // users (with roles) + managed-store assignments
            UnitSeeder::class,        // measurement units
            ItemSeeder::class,        // items + item↔store pivot
            LinkSeeder::class,        // inventory links + link↔item pivot
            EntrySeeder::class,       // entries, entry items + edit audit rows
            EventInboxSeeder::class,  // sample NATS inbox events
        ]);
    }
}
