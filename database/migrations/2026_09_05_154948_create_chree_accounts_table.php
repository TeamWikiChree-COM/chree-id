<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// php artisan make:migration create_chree_accounts_table でつくったやつー
// php artisan migrate でマイグレーションできる（こいつはたぶんテーブルつくるんやと思う）

// chree_accounts テーブルを作成する (ChreeIDアカウントのテーブル)
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('chree_accounts', function (Blueprint $table): void {
            // $table->id();
            $table->ulid('id')->primary(); // 主キー: id (ulid)
            $table->string('email')->unique()->nullable(); // メールアドレス
            $table->timestamp('email_verified_at')->nullable(); // 認証日時
            $table->string('display_name')->nullable(); // 表示名
            $table->timestamp('suspended_at')->nullable(); // 停止日時
            $table->timestamp('deleted_at')->nullable(); // 削除日時

            $table->timestamps(); // 作成日時/更新日時
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('chree_accounts');
    }
};
