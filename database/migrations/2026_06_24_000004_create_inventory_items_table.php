<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('ultimatrix_id', 100)->unique();
            $table->string('name_en', 255);
            $table->string('name_ar', 255);
            $table->string('name_es', 255);
            $table->text('details_en')->nullable();
            $table->text('details_ar')->nullable();
            $table->text('details_es')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('unit_1_id')->constrained('inventory_units');
            $table->foreignId('unit_2_id')->constrained('inventory_units');
            $table->decimal('unit_2_per_unit_1', 10, 4);
            $table->foreignId('unit_3_id')->nullable()->constrained('inventory_units');
            $table->decimal('unit_3_per_unit_2', 10, 4)->nullable();
            $table->json('types');
            $table->boolean('all_stores')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
