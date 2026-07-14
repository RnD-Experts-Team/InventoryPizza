<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items are deactivated instead of deleted. Inactive items stay in the DB
 * (so history/reports keep working) but are hidden from normal use and are
 * not auto-added to new links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('all_stores');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
