<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ログイン中の端末を一覧するための表。
//
// 実体のセッションは Laravel の sessions テーブルが持っているが、あちらは
// Laravel 標準の認証を前提にしていて user_id にこちらのアカウントIDを書けない。
// そこで「どのセッションIDがどのアカウントのものか」だけを別に持つ。
//
// この表はセッションの控えでしかない。sessions 側が消えた行は見せないこと
// (PruneLoginSessions が掃除する)。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('login_sessions', function (Blueprint $table): void {
            // sessions.id と同じ値。セッションIDは再生成されるので、行も付いて変わる
            $table->string('id')->primary();
            $table->foreignUlid('auth_identity_id')->constrained('auth_identities')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index(['auth_identity_id', 'last_active_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('login_sessions');
    }
};
