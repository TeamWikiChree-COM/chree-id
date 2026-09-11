<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 同意画面を省略するかを、信頼状態とは別のフラグにする
//
// これまでは trust=official のときだけ省略していたが、この2つは別の話。
// 「承認済みだが同意は省略したい」サービスがある (VPS-Search)。
// 公式に上げてしまうと、アカウント発行 API まで開いてしまう。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->boolean('skips_consent')->default(false);
        });

        // これまでの挙動をそのまま引き継ぐ
        DB::table('oauth_clients')->where('trust', 'official')->update(['skips_consent' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('skips_consent');
        });
    }
};
