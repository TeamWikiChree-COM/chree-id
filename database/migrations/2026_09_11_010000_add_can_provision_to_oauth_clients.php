<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// サービスアカウントを扱えるかを、信頼状態とは別のフラグにする
//
// trust=official を入場条件にしていたが、この2つは別の話。
// trust は「外部から見た信頼の表明」であって権限そのものではない。
// 「承認済みと見せたいが、遅延登録はしたい」サービスがある (VPS-Search)。
//
// 同意画面の省略 (skips_consent) を分けたのと同じ理由。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->boolean('can_provision')->default(false);
        });

        // これまでの挙動をそのまま引き継ぐ
        DB::table('oauth_clients')->where('trust', 'official')->update(['can_provision' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('can_provision');
        });
    }
};
