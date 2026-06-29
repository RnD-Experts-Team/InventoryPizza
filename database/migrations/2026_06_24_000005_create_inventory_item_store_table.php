<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('store_id', 100);
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->index(['item_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_store');
    }
};
