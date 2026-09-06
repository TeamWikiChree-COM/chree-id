<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// chree_account_aliases テーブルを作成する (ChreeIDアカウント統合履歴、過去のIDエイリアス)
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('chree_account_aliases', function (Blueprint $table) {
            $table->ulid('legacy_id')->primary(); // 統合前のID (主キー)
            $table->ulid('current_id'); // 統合後のID
            $table->timestamp('merged_at'); // 統合日時

            // current_idはchree_accountsテーブルのidを参照するように
            $table->foreign('current_id')->references('id')->on('chree_accounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('chree_account_aliases');
    }
};
