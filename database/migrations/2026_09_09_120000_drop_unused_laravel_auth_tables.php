<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Laravel の雛形が作った users と password_reset_tokens を落とす。
//
// ChreeID は Laravel 標準の認証を使わない (ChreeSession)。アカウントは
// chree_accounts、パスワード再設定は one_time_tokens が持っていて、
// この2つは作られたきり一度も使われていない。
//
// 消す理由は容量ではなく、名前が紛らわしいこと。サービス側の users
// (DokuFarm の行) の話をしているときに、こちらの users が出てくると混乱する。
//
// 同じ雛形が作った sessions は SESSION_DRIVER=database で実際に使うので残す。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
