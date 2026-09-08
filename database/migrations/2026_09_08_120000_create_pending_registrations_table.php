<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// pending_registrations テーブルを作成する (メール確認前の登録申し込み)
//
// アカウントを作るのはメールのリンクを踏んでからなので、それまでの入力をここに置く。
// one_time_tokens は chree_account_id が必須で、この時点ではアカウントが無いため使えない。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('pending_registrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // ここは unique にしない。同じアドレスで送り直せなくなるため
            $table->string('email')->index();

            $table->string('display_name')->nullable();

            // 平文は送信フォームにしか存在しない。DB にはハッシュだけ置く
            $table->string('password_hash');
            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('pending_registrations');
    }
};
