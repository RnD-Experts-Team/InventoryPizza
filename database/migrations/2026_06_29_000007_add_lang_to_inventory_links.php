<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A link carries the language chosen when it was generated. The public form
 * then shows item names/details in that single language only.
 * Existing links default to English so they keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_links', function (Blueprint $table) {
            $table->enum('lang', ['en', 'ar', 'es'])->default('en')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_links', function (Blueprint $table) {
            $table->dropColumn('lang');
        });
    }
};
