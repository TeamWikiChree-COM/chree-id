<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// auth_identities.email の一意制約を外す
//
// **メールは認証主体を一意に決めない。** ARCHITECTURE.md 8.3 が
// 「(client_id, email) を一意キーにする案は採らない」と決めているのに、
// auth_identities 側にだけ Laravel 標準の users テーブルの慣習が残っていた。
//
// 理由は同節のとおり:
//   - メールを持たない利用者が 26% いる (RESEARCH.md 2.7)
//   - メールは変わる
//   - 1:N と両立しない。同一サービスに複数アカウントを持つ人は普通そこに同じメールを使う
//
// 実害も出ていた。IssueServiceAccount が「既に使われているアドレスなら null で作る」
// という回避をしており、2人目以降はメールでログインもパスワード再設定もできなかった。
//
// どれを指すかの判断は ResolveByEmail が持つ。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('auth_identities', function (Blueprint $table): void {
            // テーブルを改名してもインデックス名は追従しないので、作られたときの名前で落とす
            $table->dropUnique('chree_accounts_email_unique');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('auth_identities', function (Blueprint $table): void {
            $table->dropIndex(['email']);
            $table->unique('email');
        });
    }
};
