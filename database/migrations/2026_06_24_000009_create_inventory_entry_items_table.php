<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('inventory_entries')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('count_unit_1', 10, 4);
            $table->decimal('count_unit_2', 10, 4);
            $table->decimal('count_unit_3', 10, 4)->default(0);
            $table->decimal('total_in_unit_1', 12, 4);
            $table->boolean('is_edited')->default(false);
            $table->timestamps();
            $table->index(['entry_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_entry_items');
    }
};
