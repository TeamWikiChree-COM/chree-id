<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// service_subject_ids テーブルを作成する (サービスごとに見せる sub)
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('service_subject_ids', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('client_id', 64);
            $table->foreignUlid('chree_account_id')->constrained('chree_accounts')->cascadeOnDelete();

            // サービスに渡す識別子。アカウント統合しても付け替えない
            $table->string('sub', 64);

            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();

            // 1サービス1アカウントにつき sub は1つ
            $table->unique(['client_id', 'chree_account_id']);
            $table->unique(['client_id', 'sub']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('service_subject_ids');
    }
};
