<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('user_name', 255);           // display-name snapshot (employee full name)
            $table->unsignedBigInteger('employee_id')->nullable(); // the counter (from HiringPizza)
            $table->unsignedBigInteger('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->date('date');
            $table->enum('type', ['daily', 'weekly', 'period']);
            $table->enum('status', ['active', 'submitted'])->default('active');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_links');
    }
};
