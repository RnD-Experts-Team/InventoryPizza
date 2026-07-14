<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Some items are counted in a single unit only. Make the second unit optional:
 * unit_2_id and unit_2_per_unit_1 become nullable. unit_1 stays required.
 *
 * Uses raw ALTER TABLE (MySQL) so we don't need doctrine/dbal for ->change().
 * The foreign key on unit_2_id is preserved — it simply allows NULL now.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE inventory_items MODIFY unit_2_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE inventory_items MODIFY unit_2_per_unit_1 DECIMAL(10,4) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_items MODIFY unit_2_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE inventory_items MODIFY unit_2_per_unit_1 DECIMAL(10,4) NOT NULL');
    }
};
