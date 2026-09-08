<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// pending_registrations から表示名とパスワードハッシュを外す。
//
// 確認前にパスワードを預かると、第三者が申し込んだパスワードのまま
// アカウントが作られる経路ができてしまう (リンクを開いた本人ではなく申込者が決めた値になる)。
// パスワードはリンクを開いたあとに設定させるので、ここで持つ必要がない。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('pending_registrations', function (Blueprint $table): void {
            $table->dropColumn(['display_name', 'password_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('pending_registrations', function (Blueprint $table): void {
            $table->string('display_name')->nullable();
            $table->string('password_hash');
        });
    }
};
