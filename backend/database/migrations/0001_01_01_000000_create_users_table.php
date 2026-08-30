<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users + sessions theo DDL y nguyên specs/1.mvp/02-db.md §1 và §3.
 * (password_reset_tokens/remember_token cắt: MVP device-based, spec 02 chỉ định 7 bảng.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->nullable();
            $table->string('email', 190)->nullable()->unique('uq_users_email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->mediumText('payload')->nullable();
            $table->unsignedInteger('last_activity')->default(0);
            $table->index('user_id', 'idx_sessions_user');
            $table->index('last_activity', 'idx_sessions_last_activity');
            $table->foreign('user_id', 'fk_sessions_user')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
