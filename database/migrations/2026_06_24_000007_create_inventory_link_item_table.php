<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_link_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('inventory_links')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->index(['link_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_link_item');
    }
};
