<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('store_id', 'employees_store_id_idx');
        });

        Schema::table('inventory_links', function (Blueprint $table) {
            $table->index('store_id', 'inventory_links_store_id_idx');
            $table->index('status',   'inventory_links_status_idx');
        });

        Schema::table('inventory_entries', function (Blueprint $table) {
            $table->index('store_id', 'inventory_entries_store_id_idx');
            $table->index('date',     'inventory_entries_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_store_id_idx');
        });

        Schema::table('inventory_links', function (Blueprint $table) {
            $table->dropIndex('inventory_links_store_id_idx');
            $table->dropIndex('inventory_links_status_idx');
        });

        Schema::table('inventory_entries', function (Blueprint $table) {
            $table->dropIndex('inventory_entries_store_id_idx');
            $table->dropIndex('inventory_entries_date_idx');
        });
    }
};
