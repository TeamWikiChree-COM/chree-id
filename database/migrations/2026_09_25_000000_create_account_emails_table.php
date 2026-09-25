<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 主アドレス (auth_identities.email) とは別に持つ追加のメールアドレス。
//
// **ログインやパスワード再設定には使わない。** 連携先のサービスへ渡すアドレスの選択肢でしかない。
// 主アドレスを auth_identities に残しているのは、メールで本人を引く処理 (ResolveByEmail) を
// 主アドレスだけに保つため。
//
// サービスごとの割り当ては service_accounts.email に文字列で持つ。null なら主アドレスを渡す。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('account_emails', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('auth_identity_id')->constrained('auth_identities')->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('verified_at')->nullable();
            // 確認待ちの間だけ入る。確認が済んだら消す
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['auth_identity_id', 'email']);
        });

        Schema::table('service_accounts', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('service_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('service_accounts', function (Blueprint $table): void {
            $table->dropColumn('email');
        });

        Schema::dropIfExists('account_emails');
    }
};
