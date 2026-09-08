<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 引き取り (claim) の一度きりの入場券を service_account_links に持たせる
//
// サービス側でログイン中の利用者にだけ URL を渡したい。
// 別テーブルにするほどの寿命も件数も無いので、リンクの列として持つ。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('service_account_links', function (Blueprint $table): void {
            // 平文はサービスに一度返すだけ。こちらはハッシュしか持たない
            $table->string('claim_token_hash', 64)->nullable()->unique();
            $table->timestamp('claim_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('service_account_links', function (Blueprint $table): void {
            $table->dropColumn(['claim_token_hash', 'claim_expires_at']);
        });
    }
};
