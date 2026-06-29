<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove the unused 'pending' value — all entries are created as 'submitted'
        DB::statement("ALTER TABLE inventory_entries MODIFY COLUMN status ENUM('submitted') NOT NULL DEFAULT 'submitted'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventory_entries MODIFY COLUMN status ENUM('pending','submitted') NOT NULL DEFAULT 'submitted'");
    }
};
