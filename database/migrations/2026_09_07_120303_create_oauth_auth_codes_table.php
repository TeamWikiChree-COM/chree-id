<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// oauth_auth_codes テーブルを作成する (認可コード)
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('oauth_auth_codes', function (Blueprint $table) {
            // 平文は redirect_uri に一度載せるだけ。DB にはハッシュしか置かない
            $table->string('code_hash', 64)->primary();

            $table->string('client_id', 64);
            $table->foreignUlid('chree_account_id')->constrained('chree_accounts')->cascadeOnDelete();

            // 発行時の値をそのまま持ち、/token で完全一致を確かめる
            $table->string('redirect_uri');
            $table->string('scope');

            // id_token にそのまま載せてリプレイを防ぐ
            $table->string('nonce')->nullable();

            $table->string('code_challenge')->nullable();
            $table->string('code_challenge_method', 8)->nullable();

            $table->timestamp('expires_at');

            // 使用済みは削除せずここを立てる。二重投入を検知して発行済みトークンごと失効させるため
            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('oauth_auth_codes');
    }
};
