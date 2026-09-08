<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// pending_email_changes テーブルを作成する (確認前のメールアドレス変更)
//
// 新しいアドレスに届くことを確かめるまで差し替えない。先に差し替えると、
// 打ち間違えただけでアカウントに二度と入れなくなる。
// one_time_tokens には行き先アドレスを持てないので別に置く。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('pending_email_changes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('chree_account_id')->constrained('chree_accounts')->cascadeOnDelete();

            $table->string('new_email');

            // 平文は発行時にしか存在しない。DB にはハッシュだけ置く
            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('chree_account_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('pending_email_changes');
    }
};
