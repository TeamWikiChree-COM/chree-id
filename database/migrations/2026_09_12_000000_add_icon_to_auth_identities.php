<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// アカウントのアイコン。
//
// 画像そのものは持たず、どこから出すか (icon_source) と、アップロードした場合の
// 保管先 (icon_path) だけを持つ。Gravatar はメールアドレスから毎回導出できるので
// URL を保存しない (メールを変えたら勝手に追従してほしい)。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('auth_identities', function (Blueprint $table): void {
            // none / gravatar / upload。IconSource の value が入る
            $table->string('icon_source', 16)->default('none');

            // upload のときだけ入る。storage の相対パス
            $table->string('icon_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('auth_identities', function (Blueprint $table): void {
            $table->dropColumn(['icon_source', 'icon_path']);
        });
    }
};
