<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_entry_item_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_item_id')->constrained('inventory_entry_items')->cascadeOnDelete();
            $table->decimal('prev_count_unit_1', 10, 4);
            $table->decimal('prev_count_unit_2', 10, 4);
            $table->decimal('prev_count_unit_3', 10, 4);
            $table->decimal('prev_total', 12, 4);
            $table->decimal('new_count_unit_1', 10, 4);
            $table->decimal('new_count_unit_2', 10, 4);
            $table->decimal('new_count_unit_3', 10, 4);
            $table->decimal('new_total', 12, 4);
            $table->text('reason');
            $table->foreignId('edited_by')->constrained('users');
            $table->timestamp('edited_at');
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — append-only table
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_entry_item_edits');
    }
};
