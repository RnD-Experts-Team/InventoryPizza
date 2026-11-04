<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Just the tables the report datasets read, created directly on the test
 * SQLite database. The migrations are not run: one of them is MySQL-only
 * (ALTER TABLE ... MODIFY COLUMN), so RefreshDatabase cannot build the schema.
 */
trait BuildsInventoryWorld
{
    protected function buildInventoryWorld(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('store_number', 100)->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->unsignedBigInteger('unit_1_id');
            $table->timestamps();
        });

        Schema::create('inventory_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->date('date');
            $table->string('type');
            $table->string('status')->default('submitted');
            $table->timestamps();
        });

        Schema::create('inventory_entry_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_id');
            $table->unsignedBigInteger('item_id');
            $table->decimal('total_in_unit_1', 12, 4);
            $table->timestamps();
        });

        DB::table('stores')->insert([
            ['id' => 1, 'store_number' => '03795-00001', 'name' => 'Downtown'],
            ['id' => 2, 'store_number' => '03795-00002', 'name' => 'Uptown'],
        ]);
        DB::table('inventory_units')->insert([['id' => 1, 'name' => 'Box'], ['id' => 2, 'name' => 'Bag']]);
        DB::table('inventory_items')->insert([
            ['id' => 1, 'name_en' => 'Dough', 'unit_1_id' => 1],
            ['id' => 2, 'name_en' => 'Sauce', 'unit_1_id' => 2],
        ]);
    }

    /**
     * @param  array<int, float>  $totals  item id => total_in_unit_1
     */
    protected function entry(int $storeId, string $date, string $type, array $totals): int
    {
        $id = DB::table('inventory_entries')->insertGetId([
            'store_id' => $storeId, 'date' => $date, 'type' => $type, 'status' => 'submitted',
        ]);

        foreach ($totals as $itemId => $total) {
            DB::table('inventory_entry_items')->insert([
                'entry_id' => $id, 'item_id' => $itemId, 'total_in_unit_1' => $total,
            ]);
        }

        return $id;
    }
}
