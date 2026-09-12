<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 2段階目を省略してよい端末。
//
// 端末に配るのは平文トークンで、こちらはハッシュしか持たない。表が漏れても
// そのまま2段階目を飛ばせる材料にはならないようにするため。
//
// **セッションとは別物。** ログアウトしても信頼は残る (次に入るとき省略したい)。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('trusted_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('auth_identity_id')->constrained('auth_identities')->cascadeOnDelete();

            // 端末に配った平文トークンの SHA-256
            $table->string('token_hash', 64)->unique();

            $table->string('label')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['auth_identity_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('trusted_devices');
    }
};
