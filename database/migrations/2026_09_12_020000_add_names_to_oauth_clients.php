<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 言語ごとのサービス名。
//
// `name` は残す。あれは運営が識別に使う名前で、どの言語でも出せる保証が要る。
// ここには本人の表示言語に合わせて出したい名前だけを入れ、無い言語は `name` を出す。
//
// 言語ごとに列を足さないのは、config/chreeid.php の locales に1行足すだけで
// 言語が増える造りにしてあるため。列にすると毎回マイグレーションが要る。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->json('names')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('names');
        });
    }
};
