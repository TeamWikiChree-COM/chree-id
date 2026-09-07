<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// oauth_access_tokens テーブルを作成する (userinfo を引くためのトークン)
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // 平文はレスポンスに一度載せるだけ。DB にはハッシュしか置かない
            $table->string('token_hash', 64)->unique();

            $table->string('client_id', 64);
            $table->foreignUlid('chree_account_id')->constrained('chree_accounts')->cascadeOnDelete();

            $table->string('scope');
            $table->timestamp('expires_at');

            // 認可コードが再利用されたとき、そのコードから出たトークンをまとめて失効させる
            $table->timestamp('revoked_at')->nullable();
            $table->string('auth_code_hash', 64)->nullable();

            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
            $table->index('auth_code_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('oauth_access_tokens');
    }
};
