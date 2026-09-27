<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ChreeID に SAML でつなぐサービス (SP)。サービスそのものは oauth_clients の行で、ここには SAML の設定だけを持つ
return new class extends Migration {
    public function up(): void {
        Schema::create('saml_service_providers', function (Blueprint $table) {
            $table->id();

            // サービスアカウント・信頼状態・同意の省略は oauth_clients の側を使う
            $table->string('client_id', 64)->unique();
            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();

            $table->string('entity_id', 500)->unique();
            $table->string('acs_url', 500);

            // あれば AuthnRequest の署名を必ず確かめる
            $table->text('certificate')->nullable();

            // 渡す属性の範囲。サービスに許したスコープの中だけ
            $table->string('scopes')->default('openid email profile');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('saml_service_providers');
    }
};
