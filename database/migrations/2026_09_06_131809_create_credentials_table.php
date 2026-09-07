<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// credentials テーブルを作成する (アカウントに紐づく認証手段)
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('chree_account_id')->constrained('chree_accounts')->cascadeOnDelete();

            // CredentialType の value が入る
            $table->string('type', 32);

            // 外部から見た識別子。パスキーの credential_id、OAuth の "google:123456" など
            // パスワードやマジックリンクのように識別子を持たない方式では null
            $table->string('identifier')->nullable();

            // 検証に使う秘密。型によって中身の性質が違うので、各 Verifier の PHPDoc に何が入るか書くこと
            //   password … bcrypt ハッシュ (不可逆)
            //   totp     … シード (可逆な暗号化。ハッシュ化してはいけない)
            //   passkey  … 公開鍵 (そもそも秘密ではない)
            //   magic_link … null。有効化の記録だけで秘密を持たない
            $table->text('secret')->nullable();

            // 型ごとの付随情報 (パスキーの sign_count、OAuth の provider 名など)
            $table->json('data')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // 同じ外部識別子が複数アカウントに紐づくのを防ぐ
            $table->unique(['type', 'identifier']);
            $table->index(['chree_account_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('credentials');
    }
};
