<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users are replicated from the Auth Service — no local login, no sessions,
        // no password reset. Only the fields we actually need.
        Schema::create('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        // password_reset_tokens: intentionally NOT created — no local password reset.
        // sessions:               intentionally NOT created — stateless API, no session auth.
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
