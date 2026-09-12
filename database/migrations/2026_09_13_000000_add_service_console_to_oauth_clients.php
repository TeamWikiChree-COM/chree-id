<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 第三者がサービスを登録できるようにするための列。
//
// owner_id は「誰が登録したか」。運営が登録したものは null のままで、
// 持ち主のいるサービスだけが本人の画面に出る。
//
// review_requested_at は承認の申請。**信頼状態は運営しか動かせない。**
// 申請はあくまで「見てほしい」という意思表示で、これ自体では何の権限にもならない。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            // 利用者に「このサービスの設定」を案内する先。サービス側が指定する
            $table->string('settings_url', 500)->nullable();

            // 登録した本人。消えても行は残す (連携している利用者がいるため)
            $table->foreignUlid('owner_id')->nullable()->constrained('auth_identities')->nullOnDelete();

            $table->timestamp('review_requested_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn(['settings_url', 'review_requested_at']);
        });
    }
};
