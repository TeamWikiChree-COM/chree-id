<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// one_time_tokens テーブルを作成する (メールで送る使い捨てトークン)
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('one_time_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('chree_account_id')->constrained('chree_accounts')->cascadeOnDelete();

            // 平文は発行時にしか存在しない。DB にはハッシュだけ置く
            $table->string('token_hash', 64)->unique();

            // login = マジックリンク, verify_email = メール検証, password_reset = パスワード再設定
            $table->string('purpose', 32);

            $table->timestamp('expires_at');

            // 使用済みは削除せずここを立てる。再利用の検知に使う
            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index(['chree_account_id', 'purpose']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('one_time_tokens');
    }
};
