<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// service_account_links テーブルを作成する (サービス側ユーザーと ChreeID の対応)
//
// サービスが「うちのこの利用者に ChreeID を1つください」と言ってきたときの記録。
// 利用者に登録を強いず、サービス利用を機に裏で発行するための土台。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('service_account_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('client_id', 40);
            $table->foreignUlid('chree_account_id')->constrained('chree_accounts')->cascadeOnDelete();

            // サービス側での利用者の識別子。向こうの主キーなど
            $table->string('service_user_id', 190);

            // 引き取り時に入力を埋めるための控え。アカウントの email には入れない。
            // 未検証のアドレスを本体に持たせると、他人のアドレスを先に押さえられてしまう
            $table->string('service_email')->nullable();

            // 引き取り (claim) が済んだ日時。済むと origin は user になる
            $table->timestamp('claimed_at')->nullable();

            $table->timestamps();

            // サービス側の1利用者につき1つ。二重発行を DB で止める
            $table->unique(['client_id', 'service_user_id']);

            // 1つの ChreeID が同じサービスで複数アカウントを持つのは許す (1:N)
            $table->index(['client_id', 'chree_account_id']);

            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('service_account_links');
    }
};
