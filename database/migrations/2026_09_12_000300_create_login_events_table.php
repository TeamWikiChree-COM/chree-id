<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 本人が見るログイン履歴。
//
// login_sessions が「いま入っている端末」なのに対し、こちらは「入った出来事」。
// セッションが切れても残す。残さないと、身に覚えのないログインに後から気付けない。
//
// **失敗も記録する。** 誰かがパスワードを試している事実は、本人にとって
// 成功と同じかそれ以上に知りたい情報になる。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('login_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('auth_identity_id')->constrained('auth_identities')->cascadeOnDelete();

            // 'password' や 'oauth:google' など。CredentialType の value が基本
            $table->string('method', 32);

            $table->boolean('succeeded');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auth_identity_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('login_events');
    }
};
