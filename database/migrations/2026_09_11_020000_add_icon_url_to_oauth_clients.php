<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// サービスのアイコン
//
// 同意画面や連携中サービスの一覧で、どのサービスか一目で分かるようにする。
// 画像そのものは持たず URL を指す。登録するのは管理者なので、
// 置き場所はサービス側に任せてよい。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->string('icon_url', 500)->nullable()->after('homepage_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('icon_url');
        });
    }
};
