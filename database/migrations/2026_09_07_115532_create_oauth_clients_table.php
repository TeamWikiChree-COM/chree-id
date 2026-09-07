<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// oauth_clients テーブルを作成する (ChreeID に接続するサービス)
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('oauth_clients', function (Blueprint $table) {
            // client_id。外部に露出するので推測しにくい値を入れる
            $table->string('id', 64)->primary();

            // confidential クライアントのみ。public クライアント (PKCE のみ) は null
            $table->string('secret_hash')->nullable();

            $table->string('name');
            $table->string('homepage_url')->nullable();

            // 許可するリダイレクト先。完全一致で照合する
            $table->json('redirect_uris');

            // 要求できるスコープ (スペース区切り)
            $table->string('scopes')->default('openid');

            // false なら public クライアント扱いで PKCE を必須にする
            $table->boolean('is_confidential')->default(true);

            // ServiceTrust の value
            $table->string('trust', 16)->default('unapproved');

            $table->timestamps();

            $table->index('trust');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('oauth_clients');
    }
};
