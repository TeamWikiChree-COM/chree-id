<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * マイグレーション実行
     */
    public function up(): void {
        Schema::table('chree_accounts', function (Blueprint $table) {
            // user = ユーザーアカウント (本人による発行), service = サービスアカウント (サービスによる発行)
            // afterとは、既存のカラムの後にカラムを追加するという意味で
            // ここではdisplay_nameのあとに差し込むということになる。
            // デフォではuserにしているけどおｋれはNOT NULLカラムはあとから既存行に追加できないので
            // まあぶっちゃけいらないのはそうだから次のやつでなおす
            // $table->string('origin', 16)->default('user')->after('display_name');

            // あと実はpostgresqlではafterは意味ないらしい
            $table->string('origin', 16)->after('display_name');

            // 本人が認証手段を登録した時刻 (いらん)
            // $table->timestamp('claimed_at')->nullable()->after('origin');
        });
    }

    /**
     * Reverse the migrations.
     * ロールバック
     */
    public function down(): void {
        Schema::table('chree_accounts', function (Blueprint $table) {
            // 取り消し用の奴、いらんけどロールバックに役に立つらしい
            $table->dropColumn(['origin']);
        });
    }
};
