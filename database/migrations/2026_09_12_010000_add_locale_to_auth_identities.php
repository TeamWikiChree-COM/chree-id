<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 本人が選んだ表示言語。
//
// null は「選んでいない」であって「日本語」ではない。既定はブラウザの Accept-Language
// なので、選ばないままにしておけば端末の設定に追従する。ここを埋めるのは本人が
// 明示的に選んだときだけ。
//
// 未ログインのときは Cookie に持つ。ここに置くのは、端末を変えても選択が付いてくるため。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('auth_identities', function (Blueprint $table): void {
            $table->string('locale', 8)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('auth_identities', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
